# Architecture

## Overview

The system is a vending machine orchestrator that manages multiple vending machines sharing a common product inventory. Each vending machine is modeled as a state machine. An orchestrator layer coordinates machine selection and product dispensing with concurrency-safe inventory access.

## System Architecture

```mermaid
graph TB
    Client([Client])

    subgraph Docker Compose
        subgraph Nginx["Nginx 1.26"]
            RP[Reverse Proxy<br/>:8000 → :80]
        end

        subgraph App["App – PHP 8.3-FPM"]
            FPM[PHP-FPM Workers<br/>:9000]
            Controllers[Controllers]
            Services[Services]
            Models[Models]
        end

        subgraph Queue["Queue Worker – PHP 8.3-FPM"]
            QW[artisan queue:work]
            Jobs[ProcessVendingMachineJob]
        end

        subgraph Postgres["PostgreSQL 16"]
            DB[(vending_machine_orchestrator)]
            Tables["vending_machines\nproducts\nsessions\njobs"]
        end

        subgraph Redis["Redis 7"]
            Cache[(Cache Store)]
        end
    end

    Client -->|HTTP :8000| RP
    RP -->|FastCGI :9000| FPM
    FPM --> Controllers --> Services --> Models

    Models -->|Read / Write\nSELECT ... FOR UPDATE| DB
    Services -->|Dispatch Job| DB
    QW -->|Poll & Process Jobs| DB
    QW --> Jobs
    Jobs -->|Update Machine State| DB

    FPM -->|Cache| Cache

    DB --- Tables

    classDef nginx fill:#4B9E4B,color:#fff,stroke:#3a7d3a
    classDef php fill:#7B68EE,color:#fff,stroke:#5b48ce
    classDef db fill:#336791,color:#fff,stroke:#264f6d
    classDef redis fill:#DC382D,color:#fff,stroke:#a82a22
    classDef client fill:#555,color:#fff,stroke:#333

    class RP nginx
    class FPM,QW php
    class DB,Tables db
    class Cache redis
    class Client client
```

## Entities

### VendingMachine

Represents a physical vending machine. Tracks its current operational state and cumulative usage count.

Fields: `id`, `name`, `status`, `usage_count`, `created_at`, `updated_at`

### Product

Represents a product type in the shared inventory. The `stock` field is the single source of truth for available quantity across all machines.

Fields: `id`, `name`, `stock`, `created_at`, `updated_at`

## State Machine

Each vending machine transitions through three states:

```mermaid
stateDiagram-v2
    [*] --> idle
    idle --> choose_product : POST /orchestrator/start-work
    choose_product --> processing : POST /orchestrator/choose-product
    processing --> idle : Background job completes

    idle --> idle : POST /vending-machines/{id}/reset (409 if already idle)
    choose_product --> idle : POST /vending-machines/{id}/reset
    processing --> idle : POST /vending-machines/{id}/reset
```

- `idle`: Machine is available for selection.
- `choose_product`: Machine has been selected by the orchestrator and is waiting for a product choice.
- `processing`: Machine is dispensing a product. A background job simulates the delivery delay and returns the machine to idle.

A manual **reset** endpoint (`POST /api/vending-machines/{id}/reset`) can force any machine back to `idle` state, regardless of its current state. This is useful for administrative recovery of stuck machines.

## Request Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant N as Nginx
    participant A as App (PHP-FPM)
    participant P as PostgreSQL
    participant Q as Queue Worker

    Note over C,Q: Purchase Flow

    C->>N: POST /api/orchestrator/start-work
    N->>A: FastCGI forward
    A->>P: BEGIN; SELECT ... FOR UPDATE (idle machines)
    P-->>A: Least-used idle machine
    A->>P: UPDATE status → choose_product; COMMIT
    A-->>N: 200 { machine }
    N-->>C: Response

    C->>N: POST /api/orchestrator/choose-product
    N->>A: FastCGI forward
    A->>P: BEGIN; LOCK machine + product rows
    A->>P: Decrement stock, set status → processing
    A->>P: INSERT job into jobs table; COMMIT
    A-->>N: 200 { machine, product }
    N-->>C: Response

    Q->>P: Poll jobs table
    P-->>Q: ProcessVendingMachineJob
    Note over Q: Simulate delivery delay
    Q->>P: UPDATE status → idle, increment usage_count
    Q->>P: DELETE job
```

## Machine Selection Algorithm

When `start_work` is called, the orchestrator selects the idle machine with the lowest `usage_count`. This ensures fair distribution of workload. If no idle machine exists, the request is rejected with HTTP 409.

The selection query acquires a row-level lock to prevent race conditions when multiple requests arrive concurrently.

## Inventory Concurrency Control

Product stock is a shared resource accessed by all machines. The `choose_product` operation runs inside a database transaction with pessimistic locking (`SELECT ... FOR UPDATE`) on the product row. This serializes concurrent stock modifications and prevents overselling.

PostgreSQL provides row-level MVCC locking, allowing high write concurrency — only rows involved in a transaction are locked, while other rows remain freely accessible.

## Background Processing

After stock is decremented, the machine enters `processing` state and a `ProcessVendingMachineJob` is dispatched to the queue. The job sleeps for a configurable duration (simulating physical delivery), then sets the machine back to `idle` and increments its `usage_count`.

The queue uses the `database` driver backed by PostgreSQL.

## Layered Architecture

- **Controllers**: Handle HTTP concerns (request/response). Delegate business logic to services.
- **Form Requests**: Validate and sanitize input before it reaches controllers.
- **Services**: Contain business logic (machine selection, purchase flow). Interact with models and dispatch jobs.
- **Models**: Eloquent models with attribute casting. Represent database entities.
- **Jobs**: Asynchronous work units processed by the queue worker.

## Infrastructure

- **Web Server**: Nginx 1.26 reverse proxy forwarding FastCGI requests to PHP-FPM.
- **Runtime**: PHP 8.3-FPM (multi-process, concurrent request handling).
- **Database**: PostgreSQL 16 with row-level locking.
- **Cache**: Redis 7.
- **Queue**: Laravel database queue driver (PostgreSQL-backed).
- **Containerization**: Docker Compose with five services:

| Service | Image / Build | Role |
|---------|---------------|------|
| `app` | Custom (PHP 8.3-FPM) | Application server |
| `nginx` | nginx:1.26-alpine | Reverse proxy on port 8000 |
| `queue` | Custom (PHP 8.3-FPM) | Background queue worker |
| `postgres` | postgres:16-alpine | Primary database |
| `redis` | redis:7-alpine | Caching layer |
