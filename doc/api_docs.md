# API Documentation

## Base URL

```
http://localhost:8000/api
```

## Interactive Documentation (Swagger UI)

Available at `http://localhost:8000/api/docs` when the application is running.

The OpenAPI specification JSON is served at `http://localhost:8000/docs/api-docs.json`.

---

## Endpoints

### Vending Machines

#### List all vending machines

```
GET /api/vending-machines
```

Response `200`:
```json
[
  {
    "id": 1,
    "name": "Machine A",
    "status": "idle",
    "usage_count": 0,
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-01-01T00:00:00.000000Z"
  }
]
```

#### Create a vending machine

```
POST /api/vending-machines
Content-Type: application/json
```

Request body:
```json
{
  "name": "Machine A"
}
```

Response `201`:
```json
{
  "id": 1,
  "name": "Machine A",
  "status": "idle",
  "usage_count": 0,
  "created_at": "2026-01-01T00:00:00.000000Z",
  "updated_at": "2026-01-01T00:00:00.000000Z"
}
```

Validation errors return `422` with field-level messages.

#### Get a vending machine

```
GET /api/vending-machines/{id}
```

Response `200`: single vending machine object.
Response `404`: machine not found.

#### Update a vending machine

```
PUT /api/vending-machines/{id}
Content-Type: application/json
```

Request body:
```json
{
  "name": "Machine A (Updated)"
}
```

Response `200`: updated vending machine object.
Response `404`: machine not found.
Response `422`: validation error.

#### Reset a vending machine to idle

Forces a vending machine back to `idle` state regardless of its current state. Useful for recovering machines stuck in `choose_product` or `processing` state.

```
POST /api/vending-machines/{id}/reset
```

No request body required.

Response `200`:
```json
{
  "message": "Machine has been reset to idle state.",
  "machine": {
    "id": 1,
    "name": "Machine A",
    "status": "idle",
    "usage_count": 0,
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-01-01T00:00:00.000000Z"
  }
}
```

Response `404`: machine not found.

Response `409`:
```json
{
  "error": "Machine is already idle."
}
```

#### Delete a vending machine

```
DELETE /api/vending-machines/{id}
```

Response `204`: no content.
Response `404`: machine not found.

---

### Products

#### List all products

```
GET /api/products
```

Response `200`:
```json
[
  {
    "id": 1,
    "name": "Cola",
    "stock": 20,
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-01-01T00:00:00.000000Z"
  }
]
```

#### Add a product

```
POST /api/products
Content-Type: application/json
```

Request body:
```json
{
  "name": "Cola",
  "stock": 100
}
```

| Field | Type    | Required | Constraints   |
|-------|---------|----------|---------------|
| name  | string  | yes      | max 255 chars |
| stock | integer | yes      | min 0         |

Response `201`: created product object.
Response `422`: validation error.

#### Update product stock

```
PATCH /api/products/{id}/stock
Content-Type: application/json
```

Request body:
```json
{
  "stock": 50
}
```

| Field | Type    | Required | Constraints |
|-------|---------|----------|-------------|
| stock | integer | yes      | min 0       |

Response `200`: updated product object.
Response `404`: product not found.
Response `422`: validation error.

#### Delete a product

```
DELETE /api/products/{id}
```

Response `204`: no content.
Response `404`: product not found.

---

### Orchestrator

#### Start work

Selects the least-used idle vending machine and transitions it to `choose_product` state.

```
POST /api/orchestrator/start-work
```

No request body required.

Response `200`:
```json
{
  "message": "Machine selected and moved to choose_product state.",
  "machine": {
    "id": 1,
    "name": "Machine A",
    "status": "choose_product",
    "usage_count": 0,
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-01-01T00:00:00.000000Z"
  }
}
```

Response `409`:
```json
{
  "error": "No idle vending machine available."
}
```

Selection algorithm: among all machines with `status = idle`, the one with the lowest `usage_count` is selected. Ties are broken by database ordering (effectively by `id`).

#### Choose product

Purchases a product through a machine that is in `choose_product` state.

```
POST /api/orchestrator/choose-product
Content-Type: application/json
```

Request body:
```json
{
  "machine_id": 1,
  "product_id": 1,
  "count": 3,
  "coins": 3
}
```

| Field      | Type    | Required | Constraints                         |
|------------|---------|----------|-------------------------------------|
| machine_id | integer | yes      | must exist in vending_machines      |
| product_id | integer | yes      | must exist in products              |
| count      | integer | yes      | min 1                               |
| coins      | integer | yes      | min 1, must equal count             |

Pricing: 1 coin per item. `coins` must equal `count`.

Response `200`:
```json
{
  "message": "Product selected. Machine is now processing.",
  "machine": {
    "id": 1,
    "name": "Machine A",
    "status": "processing",
    "usage_count": 0,
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-01-01T00:00:00.000000Z"
  },
  "product": {
    "id": 1,
    "name": "Cola",
    "stock": 17,
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-01-01T00:00:00.000000Z"
  }
}
```

Response `422` (business rule violation):
```json
{
  "error": "Coins must equal the number of products (1 coin per item)."
}
```

Other possible `422` errors:
- `"Machine is not in choose_product state."`
- `"Insufficient stock. Available: N"`

---

## State Machine Flow

A typical usage session:

1. Call `POST /api/orchestrator/start-work` to select an idle machine. The response includes the `machine_id`.
2. Call `POST /api/orchestrator/choose-product` with the `machine_id`, desired `product_id`, `count`, and `coins`.
3. The machine enters `processing` state. A background job simulates product delivery (approximately 5 seconds).
4. The machine automatically returns to `idle` state with its `usage_count` incremented by 1.

## Concurrency

The inventory (product stock) is a shared resource. All stock modifications run inside database transactions with pessimistic locking to prevent race conditions. Concurrent requests to purchase the same product are serialized safely.

## Error Responses

All error responses follow this format:

```json
{
  "error": "Description of the error."
}
```

Validation errors (422) from Laravel follow the standard format:

```json
{
  "message": "The field is required.",
  "errors": {
    "field": ["The field is required."]
  }
}
```
