#!/usr/bin/env python3

import asyncio
import json
import statistics
import sys
import time
import random
import aiohttp
from dataclasses import dataclass, field
from typing import Optional


# ─── Configuration ───────────────────────────────────────────────────────────

BASE_URL = "http://localhost:8000/api"
NUM_MACHINES = 5
NUM_PRODUCTS = 10
STOCK_PER_PRODUCT = 200
TOTAL_STOCK = NUM_PRODUCTS * STOCK_PER_PRODUCT

CONCURRENCY_LEVELS = [10, 25, 50, 100, 250, 500, 1000, 2500, 5000]

REQUEST_TIMEOUT = 30

CONNECTOR_LIMIT = 10000  # max simultaneous connections

PRODUCT_NAMES = [
    "Cola", "Pepsi", "Water", "Juice", "Coffee",
    "Tea", "Chips", "Candy", "Cookie", "Gum",
]

@dataclass
class RequestResult:
    """Result of a single HTTP request."""
    endpoint: str
    status: Optional[int] = None
    latency_ms: float = 0.0
    error: Optional[str] = None
    body: Optional[dict] = None


@dataclass
class UserResult:
    start_work: Optional[RequestResult] = None
    choose_product: Optional[RequestResult] = None

    @property
    def success(self) -> bool:
        return (
            self.start_work is not None
            and self.start_work.status == 200
            and self.choose_product is not None
            and self.choose_product.status == 200
        )

    @property
    def total_latency_ms(self) -> float:
        total = 0.0
        if self.start_work:
            total += self.start_work.latency_ms
        if self.choose_product:
            total += self.choose_product.latency_ms
        return total


@dataclass
class LevelReport:
    concurrency: int = 0
    total_users: int = 0
    successful_purchases: int = 0
    start_work_200: int = 0
    start_work_409: int = 0
    start_work_errors: int = 0
    choose_product_200: int = 0
    choose_product_422: int = 0
    choose_product_errors: int = 0
    connection_errors: int = 0
    timeout_errors: int = 0
    other_errors: int = 0
    wall_time_s: float = 0.0
    effective_rps: float = 0.0
    latencies_ms: list = field(default_factory=list)
    start_work_latencies_ms: list = field(default_factory=list)
    choose_product_latencies_ms: list = field(default_factory=list)
    error_messages: dict = field(default_factory=dict)

    @property
    def p50(self) -> float:
        return percentile(self.latencies_ms, 50)

    @property
    def p95(self) -> float:
        return percentile(self.latencies_ms, 95)

    @property
    def p99(self) -> float:
        return percentile(self.latencies_ms, 99)

    @property
    def max_latency(self) -> float:
        return max(self.latencies_ms) if self.latencies_ms else 0.0

    @property
    def avg_latency(self) -> float:
        return statistics.mean(self.latencies_ms) if self.latencies_ms else 0.0

    @property
    def sw_p50(self) -> float:
        return percentile(self.start_work_latencies_ms, 50)

    @property
    def sw_p99(self) -> float:
        return percentile(self.start_work_latencies_ms, 99)

    @property
    def cp_p50(self) -> float:
        return percentile(self.choose_product_latencies_ms, 50)

    @property
    def cp_p99(self) -> float:
        return percentile(self.choose_product_latencies_ms, 99)


def percentile(data: list, pct: int) -> float:
    if not data:
        return 0.0
    s = sorted(data)
    k = (len(s) - 1) * pct / 100
    f = int(k)
    c = f + 1 if f + 1 < len(s) else f
    return s[f] + (k - f) * (s[c] - s[f])


async def http_request(
    session: aiohttp.ClientSession,
    method: str,
    url: str,
    json_body: Optional[dict] = None,
) -> RequestResult:
    result = RequestResult(endpoint=url)
    t0 = time.monotonic()
    try:
        async with session.request(method, url, json=json_body) as resp:
            result.status = resp.status
            try:
                result.body = await resp.json()
            except Exception:
                result.body = {"raw": await resp.text()}
    except asyncio.TimeoutError:
        result.error = "timeout"
    except aiohttp.ClientConnectorError as e:
        result.error = f"connection_error: {e}"
    except aiohttp.ServerDisconnectedError:
        result.error = "server_disconnected"
    except Exception as e:
        result.error = f"exception: {type(e).__name__}: {e}"
    finally:
        result.latency_ms = (time.monotonic() - t0) * 1000
    return result


# ─── Core simulation ───

async def simulate_user(
    session: aiohttp.ClientSession,
    product_ids: list[int],
) -> UserResult:
    user = UserResult()

    # Step 1: acquire machine
    sw = await http_request(session, "POST", f"{BASE_URL}/orchestrator/start-work")
    user.start_work = sw

    if sw.status != 200 or sw.error:
        return user

    machine_id = sw.body.get("machine", {}).get("id") if sw.body else None
    if not machine_id:
        sw.error = "no_machine_id_in_response"
        return user

    # Step 2: purchase
    product_id = random.choice(product_ids)
    cp = await http_request(
        session,
        "POST",
        f"{BASE_URL}/orchestrator/choose-product",
        json_body={
            "machine_id": machine_id,
            "product_id": product_id,
            "count": 1,
            "coins": 1,
        },
    )
    user.choose_product = cp
    return user


async def reset_all_machines(session: aiohttp.ClientSession, machine_ids: list[int]):
    tasks = []
    for mid in machine_ids:
        tasks.append(
            http_request(session, "POST", f"{BASE_URL}/vending-machines/{mid}/reset")
        )
    results = await asyncio.gather(*tasks, return_exceptions=True)
    await asyncio.sleep(0.3)
    return results


async def run_level(
    session: aiohttp.ClientSession,
    concurrency: int,
    product_ids: list[int],
    machine_ids: list[int],
) -> LevelReport:
    report = LevelReport(concurrency=concurrency, total_users=concurrency)

    # Reset machines so they're all idle
    await reset_all_machines(session, machine_ids)

    # Fire all users at the same instant
    t0 = time.monotonic()
    tasks = [simulate_user(session, product_ids) for _ in range(concurrency)]
    results: list[UserResult] = await asyncio.gather(*tasks, return_exceptions=True)
    report.wall_time_s = time.monotonic() - t0

    for r in results:
        if isinstance(r, Exception):
            report.other_errors += 1
            err_key = f"gather_exception: {r}"
            report.error_messages[err_key] = report.error_messages.get(err_key, 0) + 1
            continue

        sw = r.start_work
        if sw:
            report.start_work_latencies_ms.append(sw.latency_ms)
            report.latencies_ms.append(r.total_latency_ms)
            if sw.error:
                if "timeout" in sw.error:
                    report.timeout_errors += 1
                elif "connection" in sw.error:
                    report.connection_errors += 1
                else:
                    report.other_errors += 1
                err_key = sw.error
                report.error_messages[err_key] = report.error_messages.get(err_key, 0) + 1
                report.start_work_errors += 1
            elif sw.status == 200:
                report.start_work_200 += 1
            elif sw.status == 409:
                report.start_work_409 += 1
            else:
                report.start_work_errors += 1
                err_key = f"start_work_http_{sw.status}"
                report.error_messages[err_key] = report.error_messages.get(err_key, 0) + 1
        else:
            report.other_errors += 1

        cp = r.choose_product
        if cp:
            report.choose_product_latencies_ms.append(cp.latency_ms)
            if cp.error:
                if "timeout" in cp.error:
                    report.timeout_errors += 1
                elif "connection" in cp.error:
                    report.connection_errors += 1
                else:
                    report.other_errors += 1
                err_key = cp.error
                report.error_messages[err_key] = report.error_messages.get(err_key, 0) + 1
                report.choose_product_errors += 1
            elif cp.status == 200:
                report.choose_product_200 += 1
            elif cp.status == 422:
                report.choose_product_422 += 1
                if cp.body:
                    err_key = f"choose_product_422: {cp.body.get('error', cp.body.get('message', ''))}"
                    report.error_messages[err_key] = report.error_messages.get(err_key, 0) + 1
            else:
                report.choose_product_errors += 1
                err_key = f"choose_product_http_{cp.status}"
                report.error_messages[err_key] = report.error_messages.get(err_key, 0) + 1

        if r.success:
            report.successful_purchases += 1

    report.effective_rps = concurrency / report.wall_time_s if report.wall_time_s > 0 else 0
    return report


# ─── Setup & Teardown ───────────────────────────────────────────────────────

async def setup_test_data(session: aiohttp.ClientSession) -> tuple[list[int], list[int]]:
    print("\n╔══════════════════════════════════════════════════════════╗")
    print("║           SETTING UP TEST DATA                         ║")
    print("╚══════════════════════════════════════════════════════════╝\n")

    # Get existing machines and delete them
    r = await http_request(session, "GET", f"{BASE_URL}/vending-machines")
    if r.body and isinstance(r.body, list):
        for m in r.body:
            await http_request(session, "DELETE", f"{BASE_URL}/vending-machines/{m['id']}")
        print(f"  Deleted {len(r.body)} existing machines.")

    # Get existing products and delete them
    r = await http_request(session, "GET", f"{BASE_URL}/products")
    if r.body and isinstance(r.body, list):
        for p in r.body:
            await http_request(session, "DELETE", f"{BASE_URL}/products/{p['id']}")
        print(f"  Deleted {len(r.body)} existing products.")

    # --- Create machines ---
    machine_ids = []
    for i in range(1, NUM_MACHINES + 1):
        r = await http_request(
            session, "POST", f"{BASE_URL}/vending-machines",
            json_body={"name": f"Machine-{i}"},
        )
        if r.status == 201 and r.body:
            machine_ids.append(r.body["id"])
            print(f"  ✓ Created Machine-{i} (id={r.body['id']})")
        else:
            print(f"  ✗ Failed to create Machine-{i}: {r.status} {r.body}")

    # --- Create products ---
    product_ids = []
    for i, name in enumerate(PRODUCT_NAMES):
        r = await http_request(
            session, "POST", f"{BASE_URL}/products",
            json_body={"name": name, "stock": STOCK_PER_PRODUCT},
        )
        if r.status == 201 and r.body:
            product_ids.append(r.body["id"])
            print(f"  ✓ Created {name} (id={r.body['id']}, stock={STOCK_PER_PRODUCT})")
        else:
            print(f"  ✗ Failed to create {name}: {r.status} {r.body}")

    print(f"\n  Total machines : {len(machine_ids)}")
    print(f"  Total products : {len(product_ids)}")
    print(f"  Total stock    : {len(product_ids) * STOCK_PER_PRODUCT}")
    return machine_ids, product_ids


async def get_remaining_stock(session: aiohttp.ClientSession) -> int:
    """Sum up remaining stock across all products."""
    r = await http_request(session, "GET", f"{BASE_URL}/products")
    if r.body and isinstance(r.body, list):
        return sum(p.get("stock", 0) for p in r.body)
    return -1


def print_level_report(report: LevelReport, remaining_stock: int):
    """Pretty-print one level's results to stdout."""
    print(f"\n┌──────────────────────────────────────────────────────────┐")
    print(f"│  Concurrency: {report.concurrency:>5}  │  Wall time: {report.wall_time_s:>8.2f}s  │  RPS: {report.effective_rps:>8.1f} │")
    print(f"├──────────────────────────────────────────────────────────┤")
    print(f"│  start-work   →  200: {report.start_work_200:>5}  │  409: {report.start_work_409:>5}  │  err: {report.start_work_errors:>5}  │")
    print(f"│  choose-prod  →  200: {report.choose_product_200:>5}  │  422: {report.choose_product_422:>5}  │  err: {report.choose_product_errors:>5}  │")
    print(f"│  PURCHASES    →  OK : {report.successful_purchases:>5}                               │")
    print(f"├──────────────────────────────────────────────────────────┤")
    print(f"│  Timeouts: {report.timeout_errors:>5}  │  Conn errors: {report.connection_errors:>5}  │  Other: {report.other_errors:>5}  │")
    print(f"├──────────────────────────────────────────────────────────┤")
    print(f"│  Latency (full flow, ms):                                │")
    print(f"│    p50: {report.p50:>9.1f}  │  p95: {report.p95:>9.1f}  │  p99: {report.p99:>9.1f}  │")
    print(f"│    avg: {report.avg_latency:>9.1f}  │  max: {report.max_latency:>9.1f}                  │")
    print(f"│  start-work latency   p50: {report.sw_p50:>8.1f}  p99: {report.sw_p99:>8.1f}       │")
    print(f"│  choose-product lat.  p50: {report.cp_p50:>8.1f}  p99: {report.cp_p99:>8.1f}       │")
    print(f"├──────────────────────────────────────────────────────────┤")
    print(f"│  Remaining stock: {remaining_stock:>5} / {TOTAL_STOCK}                          │")
    print(f"└──────────────────────────────────────────────────────────┘")

    if report.error_messages:
        print(f"  Error breakdown:")
        for msg, count in sorted(report.error_messages.items(), key=lambda x: -x[1]):
            print(f"    [{count:>5}x] {msg[:80]}")


def generate_markdown_report(reports: list[LevelReport], stock_snapshots: list[int]) -> str:
    """Build the final Markdown results document."""
    lines = []
    lines.append("# Stress Test Results – Vending Machine Orchestrator")
    lines.append("")
    lines.append("## Test Environment")
    lines.append("")
    lines.append("| Parameter | Value |")
    lines.append("|-----------|-------|")
    lines.append(f"| Runtime | PHP 8.3 CLI (Docker) |")
    lines.append(f"| Database | SQLite (file-based, WAL mode, single-writer) |")
    lines.append(f"| Queue | Laravel database driver |")
    lines.append(f"| Machines | {NUM_MACHINES} |")
    lines.append(f"| Products | {NUM_PRODUCTS} |")
    lines.append(f"| Stock per product | {STOCK_PER_PRODUCT} |")
    lines.append(f"| Total stock | {TOTAL_STOCK} |")
    lines.append(f"| Request timeout | {REQUEST_TIMEOUT}s |")
    lines.append(f"| Test date | {time.strftime('%Y-%m-%d %H:%M:%S')} |")
    lines.append("")

    lines.append("## Test Methodology")
    lines.append("")
    lines.append("Each concurrency level sends N simultaneous users, each executing the full")
    lines.append("purchase flow:")
    lines.append("")
    lines.append("1. `POST /api/orchestrator/start-work` – acquire an idle machine")
    lines.append("2. `POST /api/orchestrator/choose-product` – buy 1 item (if machine acquired)")
    lines.append("")
    lines.append("Between each level, all machines are reset to `idle` state. Products retain")
    lines.append("their current stock across levels (cumulative depletion).")
    lines.append("")

    # Summary table
    lines.append("## Results Summary")
    lines.append("")
    lines.append("| Concurrent Users | Wall Time (s) | RPS | Purchases OK | start-work 200 | start-work 409 | Timeouts | Conn Errors | p50 (ms) | p95 (ms) | p99 (ms) | Max (ms) | Stock Left |")
    lines.append("|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|")
    for i, r in enumerate(reports):
        stock = stock_snapshots[i] if i < len(stock_snapshots) else "?"
        lines.append(
            f"| {r.concurrency} "
            f"| {r.wall_time_s:.2f} "
            f"| {r.effective_rps:.1f} "
            f"| {r.successful_purchases} "
            f"| {r.start_work_200} "
            f"| {r.start_work_409} "
            f"| {r.timeout_errors} "
            f"| {r.connection_errors} "
            f"| {r.p50:.1f} "
            f"| {r.p95:.1f} "
            f"| {r.p99:.1f} "
            f"| {r.max_latency:.1f} "
            f"| {stock} |"
        )
    lines.append("")

    # Detailed per-endpoint latency
    lines.append("## Per-Endpoint Latency Detail")
    lines.append("")
    lines.append("| Concurrent Users | start-work p50 (ms) | start-work p99 (ms) | choose-product p50 (ms) | choose-product p99 (ms) |")
    lines.append("|---:|---:|---:|---:|---:|")
    for r in reports:
        lines.append(
            f"| {r.concurrency} "
            f"| {r.sw_p50:.1f} "
            f"| {r.sw_p99:.1f} "
            f"| {r.cp_p50:.1f} "
            f"| {r.cp_p99:.1f} |"
        )
    lines.append("")

    # Error breakdown per level
    lines.append("## Error Breakdown per Level")
    lines.append("")
    for r in reports:
        if r.error_messages:
            lines.append(f"### {r.concurrency} concurrent users")
            lines.append("")
            lines.append("| Count | Error |")
            lines.append("|------:|-------|")
            for msg, count in sorted(r.error_messages.items(), key=lambda x: -x[1]):
                lines.append(f"| {count} | {msg} |")
            lines.append("")

    # Analysis
    lines.append("## Analysis")
    lines.append("")

    # Find breaking point
    breaking_point = None
    degradation_point = None
    for r in reports:
        total_errors = r.timeout_errors + r.connection_errors + r.other_errors + r.start_work_errors + r.choose_product_errors
        if total_errors > 0 and degradation_point is None:
            degradation_point = r.concurrency
        if (r.timeout_errors + r.connection_errors) > r.concurrency * 0.1 and breaking_point is None:
            breaking_point = r.concurrency

    lines.append("### Throughput")
    lines.append("")
    max_rps_report = max(reports, key=lambda r: r.effective_rps)
    lines.append(f"- **Peak effective RPS**: {max_rps_report.effective_rps:.1f} "
                 f"(at {max_rps_report.concurrency} concurrent users)")
    max_purchases = max(reports, key=lambda r: r.successful_purchases)
    lines.append(f"- **Max successful purchases in one burst**: {max_purchases.successful_purchases} "
                 f"(at {max_purchases.concurrency} concurrent users)")
    lines.append("")

    lines.append("### Bottlenecks")
    lines.append("")
    lines.append("1. **Machine Availability (5 machines)**: With only 5 vending machines, at most 5 "
                 "users can acquire a machine simultaneously. All other concurrent requests receive "
                 "HTTP 409 (\"No idle vending machine available\"). This is the primary throughput limiter.")
    lines.append("2. **SQLite Single-Writer Lock**: SQLite serializes all write transactions. Under high "
                 "concurrency, `lockForUpdate()` calls queue up, dramatically increasing response times "
                 "and eventually causing timeouts or `SQLITE_BUSY` errors.")
    lines.append("3. **PHP Built-in Server (single-threaded)**: The `php artisan serve` development "
                 "server processes requests sequentially, creating an additional bottleneck under load.")
    lines.append("4. **5-Second Processing Delay**: After each purchase, the machine enters `processing` "
                 "state for 5 seconds. This limits sustained throughput to ~1 purchase/second across "
                 "all 5 machines.")
    lines.append("")

    lines.append("### Breaking Point")
    lines.append("")
    if breaking_point:
        lines.append(f"The system shows significant failures starting at **{breaking_point} concurrent users**. "
                     "At this level, more than 10% of requests experience timeouts or connection errors, "
                     "indicating the server can no longer handle the load.")
    elif degradation_point:
        lines.append(f"Performance degradation (non-timeout errors) begins at **{degradation_point} concurrent users**, "
                     "but the system does not fully break within the tested range.")
    else:
        lines.append("The system handled all concurrency levels without hard failures, though throughput "
                     "is naturally capped by the 5-machine limit.")
    lines.append("")

    lines.append("### Latency Scaling")
    lines.append("")
    if len(reports) >= 2:
        first = reports[0]
        last = reports[-1]
        lines.append(f"- At {first.concurrency} users: p50={first.p50:.1f}ms, p99={first.p99:.1f}ms")
        lines.append(f"- At {last.concurrency} users: p50={last.p50:.1f}ms, p99={last.p99:.1f}ms")
        if first.p99 > 0:
            factor = last.p99 / first.p99
            lines.append(f"- p99 latency increased by **{factor:.1f}x** from {first.concurrency} to {last.concurrency} users")
    lines.append("")

    lines.append("### Concurrency Control Effectiveness")
    lines.append("")
    total_purchased = sum(r.successful_purchases for r in reports)
    final_stock = stock_snapshots[-1] if stock_snapshots else "?"
    expected_remaining = TOTAL_STOCK - total_purchased
    lines.append(f"- Total successful purchases across all levels: **{total_purchased}**")
    lines.append(f"- Expected remaining stock: **{expected_remaining}**")
    lines.append(f"- Actual remaining stock: **{final_stock}**")
    if isinstance(final_stock, int) and final_stock == expected_remaining:
        lines.append("- ✅ **No overselling detected** – pessimistic locking is working correctly.")
    elif isinstance(final_stock, int):
        lines.append(f"- ⚠️ **Stock mismatch detected** (diff={expected_remaining - final_stock}). "
                     "This may indicate a race condition or double-decrement.")
    lines.append("")

    lines.append("## Recommendations")
    lines.append("")
    lines.append("To improve concurrency handling for production workloads:")
    lines.append("")
    lines.append("1. **Replace SQLite with PostgreSQL/MySQL** – Proper MVCC and row-level locking allow "
                 "much higher write concurrency.")
    lines.append("2. **Use a production web server** – Deploy behind Nginx + PHP-FPM (or Laravel Octane "
                 "with Swoole/RoadRunner) for parallel request processing.")
    lines.append("3. **Add more vending machines** – The 5-machine limit is an artificial bottleneck; "
                 "scale horizontally.")
    lines.append("4. **Reduce processing delay** – The 5-second simulated delivery time severely limits "
                 "throughput. In production, use async/event-driven patterns.")
    lines.append("5. **Implement request queuing** – Instead of rejecting users immediately when no "
                 "machine is available, queue them and serve on machine availability.")
    lines.append("6. **Use Redis for queue** – The database queue driver adds extra write contention "
                 "to the already-bottlenecked SQLite file.")
    lines.append("")

    return "\n".join(lines)

async def main():
    print("=" * 60)
    print("  VENDING MACHINE ORCHESTRATOR – STRESS TEST")
    print("  Simulating up to 5,000 concurrent users")
    print("=" * 60)

    timeout = aiohttp.ClientTimeout(total=REQUEST_TIMEOUT)
    connector = aiohttp.TCPConnector(limit=CONNECTOR_LIMIT, force_close=True)

    async with aiohttp.ClientSession(timeout=timeout, connector=connector) as session:
        # Check API is reachable
        r = await http_request(session, "GET", f"{BASE_URL}/products")
        if r.error or r.status is None:
            print(f"\n✗ Cannot reach API at {BASE_URL}")
            print(f"  Error: {r.error}")
            print("  Make sure 'docker compose up' is running.")
            sys.exit(1)
        print(f"\n✓ API reachable at {BASE_URL}")

        # Setup
        machine_ids, product_ids = await setup_test_data(session)
        if not machine_ids or not product_ids:
            print("\n✗ Failed to set up test data. Aborting.")
            sys.exit(1)

        # Run stress tests
        reports: list[LevelReport] = []
        stock_snapshots: list[int] = []

        print("\n╔══════════════════════════════════════════════════════════╗")
        print("║           RUNNING STRESS TESTS                         ║")
        print("╚══════════════════════════════════════════════════════════╝")

        for level in CONCURRENCY_LEVELS:
            print(f"\n{'─' * 60}")
            print(f"  ▸ Testing {level} concurrent users ...")

            report = await run_level(session, level, product_ids, machine_ids)
            stock = await get_remaining_stock(session)

            reports.append(report)
            stock_snapshots.append(stock)

            print_level_report(report, stock)

            await asyncio.sleep(1)

        print("\n" + "=" * 60)
        print("  STRESS TEST COMPLETE")
        print("=" * 60)

        total_purchased = sum(r.successful_purchases for r in reports)
        final_stock = stock_snapshots[-1] if stock_snapshots else -1
        expected = TOTAL_STOCK - total_purchased
        print(f"\n  Total purchases across all levels: {total_purchased}")
        print(f"  Expected remaining stock: {expected}")
        print(f"  Actual remaining stock:   {final_stock}")
        if final_stock == expected:
            print("  ✅ Stock integrity verified – no overselling!")
        else:
            print(f"  ⚠️ Stock mismatch! Difference: {expected - final_stock}")

        md = generate_markdown_report(reports, stock_snapshots)
        report_path = "doc/stress_test_results2.md"
        with open(report_path, "w") as f:
            f.write(md)
        print(f"\n  📄 Full report written to: {report_path}")

        # Also write raw JSON data
        json_path = "doc/stress_test_raw2.json"
        raw_data = []
        for i, r in enumerate(reports):
            raw_data.append({
                "concurrency": r.concurrency,
                "wall_time_s": round(r.wall_time_s, 3),
                "effective_rps": round(r.effective_rps, 1),
                "successful_purchases": r.successful_purchases,
                "start_work_200": r.start_work_200,
                "start_work_409": r.start_work_409,
                "start_work_errors": r.start_work_errors,
                "choose_product_200": r.choose_product_200,
                "choose_product_422": r.choose_product_422,
                "choose_product_errors": r.choose_product_errors,
                "timeout_errors": r.timeout_errors,
                "connection_errors": r.connection_errors,
                "other_errors": r.other_errors,
                "latency_p50_ms": round(r.p50, 1),
                "latency_p95_ms": round(r.p95, 1),
                "latency_p99_ms": round(r.p99, 1),
                "latency_max_ms": round(r.max_latency, 1),
                "latency_avg_ms": round(r.avg_latency, 1),
                "stock_remaining": stock_snapshots[i] if i < len(stock_snapshots) else None,
                "error_messages": r.error_messages,
            })
        with open(json_path, "w") as f:
            json.dump(raw_data, f, indent=2)
        print(f"  📄 Raw data written to: {json_path}")


if __name__ == "__main__":
    asyncio.run(main())
