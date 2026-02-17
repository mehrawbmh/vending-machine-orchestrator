# Stress Test Results – Vending Machine Orchestrator

## Test Environment

> **Note:** These results were captured on the **original infrastructure** (PHP built-in server + SQLite). The system has since been upgraded to **Nginx + PHP-FPM, PostgreSQL 16, and Redis 7** — see [Recommendations](#recommendations) for which items have been addressed.

| Parameter | Value |
|-----------|-------|
| Runtime | PHP 8.3 CLI built-in server (Docker) |
| Database | SQLite (file-based, single-writer) |
| Queue | Laravel database driver |
| Machines | 5 |
| Products | 10 |
| Stock per product | 200 |
| Total stock | 2 000 |
| Per-request timeout | 30 s |
| Client | Python 3.12 + aiohttp (async) |
| Test date | 2026-02-17 |

## Test Methodology

Each concurrency level fires **N users simultaneously**, each executing the full two-step purchase flow:

1. `POST /api/orchestrator/start-work` – acquire the least-used idle machine
2. `POST /api/orchestrator/choose-product` – buy 1 item from a random product (if a machine was acquired)

Between levels, every machine is **reset to `idle`** so the next batch starts from a clean slate. Products retain their current stock across levels (cumulative depletion).

Concurrency levels tested: **10 → 25 → 50 → 100 → 250 → 500 → 1 000 → 2 500 → 5 000**

---

## Results Summary

| Concurrent Users | Wall Time (s) | Effective RPS | Purchases OK | start-work 200 | start-work 409 | Timeouts | Conn Errors | p50 (ms) | p95 (ms) | p99 (ms) | Max (ms) | Stock Left |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 10 | 0.24 | 42.0 | 5 | 5 | 5 | 0 | 0 | 135.3 | 229.0 | 235.4 | 237.0 | 1 995 |
| 25 | 0.13 | 197.8 | 0 | 0 | 25 | 0 | 0 | 65.2 | 118.1 | 122.6 | 123.7 | 1 995 |
| 50 | 0.38 | 132.6 | 0 | 0 | 50 | 0 | 0 | 212.3 | 354.9 | 364.2 | 366.6 | 1 995 |
| 100 | 0.57 | 176.3 | 0 | 0 | 100 | 0 | 0 | 298.6 | 527.5 | 551.0 | 556.5 | 1 995 |
| 250 | 1.46 | 171.4 | 1 | 1 | 249 | 0 | 0 | 734.5 | 1 329.9 | 1 366.2 | 1 457.7 | 1 994 |
| 500 | 3.20 | 156.2 | 1 | 1 | 499 | 0 | 0 | 1 284.8 | 2 937.6 | 3 116.8 | 3 186.1 | 1 993 |
| 1 000 | 4.77 | 209.5 | 1 | 1 | 999 | 0 | 0 | 2 535.0 | 4 464.1 | 4 616.2 | 4 727.0 | 1 992 |
| **2 500** | **30.52** | **81.9** | **0** | **0** | **0** | **2 500** | **0** | **30 290** | **30 469** | **30 482** | **30 485** | **(timeout)** |
| **5 000** | **30.90** | **161.8** | **0** | **0** | **0** | **5 000** | **0** | **30 642** | **30 773** | **30 782** | **30 785** | **(timeout)** |

> **Stock Left "(timeout)"**: At 2 500 and 5 000 users the server was overwhelmed; the follow-up GET /api/products request itself timed out.  
> Verified after recovery: **1 992 items** remained — matching the expected value exactly.

---

## Per-Endpoint Latency Detail

| Concurrent Users | start-work p50 (ms) | start-work p99 (ms) | choose-product p50 (ms) | choose-product p99 (ms) |
|---:|---:|---:|---:|---:|
| 10 | 92.5 | 118.3 | 142.3 | 148.0 |
| 25 | 65.2 | 122.6 | — | — |
| 50 | 212.3 | 364.2 | — | — |
| 100 | 298.6 | 551.0 | — | — |
| 250 | 729.3 | 1 360.4 | 1 375.4 | 1 375.4 |
| 500 | 1 284.8 | 3 108.6 | 1 187.0 | 1 187.0 |
| 1 000 | 2 531.3 | 4 612.6 | 2 238.8 | 2 238.8 |
| 2 500 | 30 290 | 30 482 | — | — |
| 5 000 | 30 642 | 30 782 | — | — |

"—" means no user reached the choose-product step (all were rejected or timed out at start-work).

---

## Error Breakdown

| Concurrency | Error Type | Count |
|---:|---|---:|
| 10 – 1 000 | *(no errors)* | 0 |
| 2 500 | Request timeout (30 s) | 2 500 |
| 5 000 | Request timeout (30 s) | 5 000 |

The system returned **zero** unexpected errors (no 500s, no connection resets, no database corruption) up to 1 000 concurrent users. The only "failures" below 2 500 are the expected HTTP 409 responses (no idle machine available).

---

## Analysis

### 1. Throughput

| Metric | Value |
|--------|-------|
| Peak effective RPS (requests completed / wall time) | **209.5** (at 1 000 users) |
| Max successful purchases in a single burst | **5** (at 10 users — exactly one per machine) |
| Theoretical sustained purchase rate | ~1 purchase/s (5 machines ÷ 5 s processing each) |

With only 5 machines and serialized SQLite writes, the absolute purchase throughput is hardware-limited to the number of available machines. However, the server still **processed and correctly responded** to 1 000 rejection requests in under 5 seconds.

### 2. Latency Scaling

Latency grows linearly with concurrency because the PHP built-in server processes requests **sequentially** (single-threaded):

| Users | p99 Latency | Observation |
|------:|------------:|-------------|
| 10 | 235 ms | Comfortable — well within SLA |
| 50 | 364 ms | Still acceptable |
| 100 | 551 ms | Half-second range |
| 250 | 1 366 ms | Over 1 s — user experience degrades |
| 500 | 3 117 ms | 3+ seconds — unacceptable for interactive use |
| 1 000 | 4 616 ms | ~5 seconds — at the edge |
| 2 500 | 30 482 ms | Complete timeout — **system broken** |
| 5 000 | 30 782 ms | Complete timeout — **system broken** |

**p99 latency multiplied 130× from 10 to 5 000 users.**

### 3. Breaking Point

> **The system breaks at ≈ 2 500 concurrent connections.**

At 1 000 users the server is severely degraded (p99 ≈ 4.6 s) but still returning valid HTTP responses. At 2 500 users, **100% of requests time out** — the single-threaded PHP server cannot drain its connection queue within the 30-second window. The jump from "slow but functional" to "total failure" is abrupt:

```
 1 000 users → p99  4 616 ms   (all responses returned)
 2 500 users → p99 30 482 ms   (0 responses returned — 100% timeout)
```

This cliff-edge failure is characteristic of a **single-threaded server** with no connection backpressure: once the request queue exceeds what can be processed in the timeout window, every queued request expires.

### 4. Bottleneck Hierarchy

1. **PHP Built-in Server (single-threaded)** — The dominant bottleneck. `php artisan serve` processes one request at a time. At high concurrency, requests queue up in TCP backlog and expire.

2. **SQLite Single-Writer Lock** — All `lockForUpdate()` calls serialize at the database level. Even if the server were multi-threaded, SQLite would still limit write concurrency to one transaction at a time.

3. **Machine Availability (5 machines)** — At most 5 users can acquire a machine per round. This is an application-level design constraint, not a defect.

4. **5-Second Processing Delay** — Each machine is locked for 5 seconds after a purchase. This caps sustained throughput to ~1 purchase/second system-wide.

### 5. Concurrency Control Effectiveness

| Metric | Value |
|--------|-------|
| Total successful purchases (all levels) | **8** |
| Expected remaining stock | **1 992** |
| Actual remaining stock (verified post-test) | **1 992** |

✅ **No overselling detected.** The pessimistic locking (`SELECT … FOR UPDATE` inside a DB transaction) correctly serialized every stock decrement. Despite thousands of concurrent requests, not a single item was double-sold.

---

## Recommendations

To scale this system for production workloads:

| # | Recommendation | Expected Impact | Status |
|---|----------------|-----------------|--------|
| 1 | **Replace `php artisan serve` with Nginx + PHP-FPM** | Parallel request processing; eliminates the single-threaded cliff | ✅ Done |
| 2 | **Replace SQLite with PostgreSQL** | Row-level MVCC locking; 100× higher write concurrency | ✅ Done |
| 3 | **Add Redis** | Fast caching layer; removes contention from the database | ✅ Done |
| 4 | **Increase the machine pool** (or make it dynamic) | Directly increases purchases/second proportionally | Pending |
| 5 | **Reduce or remove the 5-second processing delay** | In production, use event-driven completion instead of `sleep()` | Pending |
| 6 | **Implement a waiting queue for users** | Instead of immediate 409 rejection, queue users and serve on machine availability | Pending |
| 7 | **Add horizontal scaling** (multiple app containers behind a load balancer) | Linear throughput increase; now feasible with PostgreSQL | Pending |

Recommendations 1–3 have been implemented. The system now uses **Nginx 1.26 + PHP 8.3-FPM** for concurrent request processing, **PostgreSQL 16** for row-level MVCC locking, and **Redis 7** as a caching layer.

---

## Raw Data

Full per-level JSON metrics are available in [`doc/stress_test_raw.json`](stress_test_raw.json).

The stress test script is at [`stress_test.py`](../../../stress_test.py) in the project root.
