# Vending Machine Orchestrator

A RESTful API service that manages multiple vending machines sharing a common product inventory. Built with Laravel 12, PHP 8.3-FPM, Nginx, PostgreSQL, and Redis.

## Requirements

- Docker
- Docker Compose

## Setup

```
cp .env.example .env
docker compose up --build
```

The API will be available at `http://localhost:8000`.

This command builds the images, installs dependencies, generates the application key, runs migrations, generates Swagger docs, and starts all services:

| Service | Role |
|---------|------|
| **app** | PHP 8.3-FPM application server |
| **nginx** | Reverse proxy, serves static files and forwards PHP requests to FPM |
| **queue** | Laravel queue worker for background job processing |
| **postgres** | PostgreSQL 16 database |
| **redis** | Redis 7 for caching |

To stop:

```
docker compose down
```

To reset the database (removes all data):

```
docker compose down -v
docker compose up --build
```

## API Endpoints

### Vending Machines

| Method | Endpoint | Description |
|--------|--------------------------------------|--------------------------|
| GET | /api/vending-machines | List all machines |
| POST | /api/vending-machines | Create a machine |
| GET | /api/vending-machines/{id} | Get a machine |
| PUT | /api/vending-machines/{id} | Update a machine |
| POST | /api/vending-machines/{id}/reset | Reset machine to idle |
| DELETE | /api/vending-machines/{id} | Delete a machine |

### Products

| Method | Endpoint | Description |
|--------|--------------------------------|--------------------------|
| GET | /api/products | List all products |
| POST | /api/products | Add a product |
| PATCH | /api/products/{id}/stock | Update product stock |
| DELETE | /api/products/{id} | Delete a product |

### Orchestrator

| Method | Endpoint | Description |
|--------|--------------------------------------|----------------------------------------------|
| POST | /api/orchestrator/start-work | Select least-used idle machine |
| POST | /api/orchestrator/choose-product | Purchase product through selected machine |


Coins must equal count (1 coin per item). The machine must be in `choose_product` state (set by start-work). After purchase, the machine enters `processing` state and returns to `idle` automatically after the background job completes.

## Interactive API Docs

Swagger UI is available at `http://localhost:8000/api/docs` when the application is running.

## Testing

Run the test suite inside Docker:

```
docker compose exec app php artisan test
```

## Architecture

See `doc/architecture.md` for design details.
See `doc/api_docs.md` for detailed API documentation.
