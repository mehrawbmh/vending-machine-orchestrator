# Stress Test Results – Vending Machine Orchestrator

## Test Environment

| Parameter | Value                          |
|-----------|--------------------------------|
| Runtime | PHP 8.3 FPM (Nginx - Docker)   |
| Database | PostgreSQL (row-level locking) |
| Queue | Laravel database driver        |
| Machines | 5                              |
| Products | 10                             |
| Stock per product | 200                            |
| Total stock | 2000                           |
| Request timeout | 30s                            |
| Test date | 2026-02-17 19:28:32            |

## Test Methodology

Each concurrency level sends N simultaneous users, each executing the full
purchase flow:

1. `POST /api/orchestrator/start-work` – acquire an idle machine
2. `POST /api/orchestrator/choose-product` – buy 1 item (if machine acquired)

Between each level, all machines are reset to `idle` state. Products retain
their current stock across levels (cumulative depletion).

## Results Summary

| Concurrent Users | Wall Time (s) | RPS | Purchases OK | start-work 200 | start-work 409 | Timeouts | Conn Errors | p50 (ms) | p95 (ms) | p99 (ms) | Max (ms) | Stock Left |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 10 | 0.26 | 38.2 | 5 | 5 | 5 | 0 | 0 | 192.2 | 247.9 | 257.0 | 259.3 | 1995 |
| 25 | 0.34 | 73.7 | 5 | 5 | 20 | 0 | 0 | 227.6 | 330.4 | 336.2 | 336.5 | 1990 |
| 50 | 0.52 | 96.7 | 5 | 5 | 45 | 0 | 0 | 281.2 | 481.1 | 499.5 | 507.5 | 1985 |
| 100 | 1.03 | 97.2 | 5 | 5 | 95 | 0 | 0 | 447.8 | 916.5 | 982.3 | 1026.0 | 1980 |
| 250 | 2.28 | 109.9 | 5 | 5 | 245 | 0 | 0 | 1241.3 | 2133.4 | 2260.6 | 2272.5 | 1975 |
| 500 | 3.33 | 150.3 | 5 | 5 | 495 | 0 | 0 | 1747.6 | 3080.2 | 3189.9 | 3323.2 | 1970 |
| 1000 | 7.16 | 139.6 | 5 | 7 | 993 | 0 | 0 | 3780.2 | 6648.9 | 6907.9 | 7158.2 | 1965 |
| 2500 | 17.54 | 142.5 | 5 | 9 | 2491 | 0 | 0 | 8787.3 | 16595.4 | 16971.4 | 17508.5 | 1960 |
| 5000 | 35.76 | 139.8 | 2 | 11 | 4298 | 696 | 0 | 18178.9 | 30269.2 | 30300.7 | 35590.9 | 1954 |

## Per-Endpoint Latency Detail

| Concurrent Users | start-work p50 (ms) | start-work p99 (ms) | choose-product p50 (ms) | choose-product p99 (ms) |
|---:|---:|---:|---:|---:|
| 10 | 121.0 | 176.9 | 151.2 | 162.6 |
| 25 | 185.5 | 272.7 | 245.0 | 271.9 |
| 50 | 246.8 | 436.3 | 424.7 | 441.2 |
| 100 | 404.1 | 910.5 | 905.0 | 925.6 |
| 250 | 1203.3 | 2167.7 | 2153.6 | 2163.4 |
| 500 | 1720.7 | 3163.8 | 3181.4 | 3188.1 |
| 1000 | 3753.3 | 6857.9 | 6860.1 | 6873.2 |
| 2500 | 8719.4 | 16934.8 | 16817.2 | 16819.9 |
| 5000 | 18109.6 | 30297.5 | 28286.1 | 30678.2 |

## Error Breakdown per Level

### 1000 concurrent users

| Count | Error |
|------:|-------|
| 2 | choose_product_422: Machine is not in choose_product state. |

### 2500 concurrent users

| Count | Error |
|------:|-------|
| 4 | choose_product_422: Machine is not in choose_product state. |

### 5000 concurrent users

| Count | Error |
|------:|-------|
| 696 | timeout |
| 4 | choose_product_422: Machine is not in choose_product state. |

## Analysis

### Throughput

- **Peak effective RPS**: 150.3 (at 500 concurrent users)
- **Max successful purchases in one burst**: 5 (at 10 concurrent users)

### Bottlenecks

1. **Machine Availability (5 machines)**: With only 5 vending machines, at most 5 users can acquire a machine simultaneously. All other concurrent requests receive HTTP 409 ("No idle vending machine available"). This is the primary throughput limiter.
2. **SQLite Single-Writer Lock**: SQLite serializes all write transactions. Under high concurrency, `lockForUpdate()` calls queue up, dramatically increasing response times and eventually causing timeouts or `SQLITE_BUSY` errors.
3. **PHP Built-in Server (single-threaded)**: The `php artisan serve` development server processes requests sequentially, creating an additional bottleneck under load.
4. **5-Second Processing Delay**: After each purchase, the machine enters `processing` state for 5 seconds. This limits sustained throughput to ~1 purchase/second across all 5 machines.

### Breaking Point

The system shows significant failures starting at **5000 concurrent users**. At this level, more than 10% of requests experience timeouts or connection errors, indicating the server can no longer handle the load.

### Latency Scaling

- At 10 users: p50=192.2ms, p99=257.0ms
- At 5000 users: p50=18178.9ms, p99=30300.7ms
- p99 latency increased by **117.9x** from 10 to 5000 users

### Concurrency Control Effectiveness

- Total successful purchases across all levels: **42**
- Expected remaining stock: **1958**
- Actual remaining stock: **1954**
- ⚠️ **Stock mismatch detected** (diff=4). This may indicate a race condition or double-decrement.

## Recommendations

To improve concurrency handling for production workloads:

1. **Replace SQLite with PostgreSQL/MySQL** – Proper MVCC and row-level locking allow much higher write concurrency.
2. **Use a production web server** – Deploy behind Nginx + PHP-FPM (or Laravel Octane with Swoole/RoadRunner) for parallel request processing.
3. **Add more vending machines** – The 5-machine limit is an artificial bottleneck; scale horizontally.
4. **Reduce processing delay** – The 5-second simulated delivery time severely limits throughput. In production, use async/event-driven patterns.
5. **Implement request queuing** – Instead of rejecting users immediately when no machine is available, queue them and serve on machine availability.
6. **Use Redis for queue** – The database queue driver adds extra write contention to the already-bottlenecked SQLite file.
