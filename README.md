# Ticketing System — High-Concurrency Ticket Reservation

A ticket reservation and purchasing system built to demonstrate **Hexagonal Architecture**, **Domain-Driven Design**, and **high-concurrency data integrity** under extreme load.

> **Portfolio Focus:** This project showcases how to prevent race conditions when 1,000 users simultaneously compete for 100 tickets.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Architecture Overview](#architecture-overview)
3. [Core Flow: Purchasing a Ticket](#core-flow-purchasing-a-ticket)
4. [Tech Stack](#tech-stack)
5. [API Endpoints](#api-endpoints)
6. [Testing](#testing)
7. [Project Structure](#project-structure)
8. [Contribution Workflow](#contribution-workflow)

---

## Quick Start

### Prerequisites
- Docker & Docker Compose

### Installation

```bash
# 1. Start containers
docker compose up -d

# 2. Install dependencies & generate key
docker compose exec laravel composer install
docker compose exec laravel php artisan key:generate

# 3. Run migrations
docker compose exec laravel php artisan migrate

# 4. (Optional) Seed stress-test data
docker compose exec laravel php artisan db:seed --class=StressTestSeeder

# 5. Generate API docs
docker compose exec laravel php artisan l5-swagger:generate
```

The API will be available at `http://localhost` and Swagger docs at `http://localhost/api/documentation`.

---

## Architecture Overview

This project follows **Hexagonal Architecture (Ports & Adapters)** with strict dependency rules:

```
┌─────────────────────────────────────────┐
│           Infrastructure                │  ← Laravel, Redis, MySQL
│  (Controllers, Repositories, Jobs)      │
├─────────────────────────────────────────┤
│           Application                   │  ← Use Cases, DTOs, Ports
│  (PurchaseTicketUseCase, Queries)       │
├─────────────────────────────────────────┤
│           Domain                        │  ← Pure business logic
│  (Ticket, Seat, Reservation, Events)    │
└─────────────────────────────────────────┘
```

**Dependency Rule:** Domain knows nothing about frameworks. Infrastructure depends on Application and Domain.

### Bounded Contexts

| Context | Responsibility |
|---------|---------------|
| `Ticketing` | Events, seats, reservations, tickets, season tickets |
| `Security` | Authentication via Sanctum, rate limiting |
| `Reports` | Admin report downloads |
| `Shared` | Audit logging, health checks, base classes |

---

## Core Flow: Purchasing a Ticket

The [`PurchaseTicketUseCase`](src/Ticketing/Application/UseCases/PurchaseTicketUseCase.php) is the star of the system. It guarantees that **exactly one user gets each seat** even under extreme concurrency.

```
POST /api/tickets/purchase
Header: Idempotency-Key: <uuid-v4>
Body:   { "event_id": 1, "seat_id": 42 }
```

### Step-by-Step

| Step | Layer | What Happens |
|------|-------|-------------|
| 1 | Controller | Validates `Idempotency-Key` (UUID v4) and input |
| 2 | Application | Checks idempotency store (Redis) — duplicate? Return previous result |
| 3 | Application | Atomically decrements stock in Redis (fast fail if sold out) |
| 4 | Application | Starts DB transaction + `SELECT ... FOR UPDATE` on seat row |
| 5 | Domain | `$seat->reserve($userId)` — state change + validation |
| 6 | Domain | `Reservation::create(...)` — new aggregate |
| 7 | Application | Commits transaction, releases DB lock |
| 8 | Application | Dispatches async payment job (Saga pattern) |
| 9 | Application | Stores result in idempotency store |
| 10 | Domain | `TicketSold` domain event recorded for listeners |

### Concurrency Safeguards

- **Redis Atomic Lock** — First barrier; prevents unnecessary DB load
- **Pessimistic DB Locking** — `lockForUpdate()` guarantees row-level isolation
- **Idempotency** — Same `Idempotency-Key` always returns the same result, never double-charges
- **Circuit Breaker** — [`RedisCircuitBreaker`](src/Ticketing/Infrastructure/Payment/RedisCircuitBreaker.php) prevents cascading payment failures

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.4 (strict types, readonly classes, enums) |
| Framework | Laravel 12 (delivery mechanism only) |
| Database | MySQL 8.0 (InnoDB, READ COMMITTED) |
| Cache/Locking | Redis (atomic Lua scripts, distributed locks) |
| Auth | Laravel Sanctum |
| API Docs | L5-Swagger / OpenAPI 3 |
| Testing | PHPUnit + k6 (load/stress) |
| CI | GitHub Actions + Docker |

---

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Authenticate (rate-limited) |
| GET | `/api/health` | Health probe |
| GET | `/api/readiness` | Readiness probe (DB + cache) |

### Authenticated
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/events/{id}/seats` | List available seats (cursor pagination) |
| POST | `/api/tickets/purchase` | Purchase single ticket |
| POST | `/api/season-tickets/purchase` | Purchase season ticket |
| POST | `/api/season-tickets/{id}/pay` | Pay pending season ticket |
| POST | `/api/logout` | Revoke current token |
| POST | `/api/refresh-token` | Rotate token |

### Admin Only
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/events/{id}/stats` | Event sales statistics |
| GET | `/api/reports/download` | Download report file |
| POST | `/api/users/{id}/tokens/revoke-all` | Revoke all user tokens |

---

## Testing

### Test Pyramid

```
      /
     /  \     Acceptance (HTTP end-to-end)
    /____\
   /      \   Integration (DB, Redis, Jobs)
  /________\
 /          \ Unit (Domain logic, no framework)
/____________\
```

### Running Tests

```bash
# All tests
docker compose exec laravel php artisan test

# By suite
docker compose exec laravel php artisan test tests/Ticketing/Unit
docker compose exec laravel php artisan test tests/Ticketing/Integration
docker compose exec laravel php artisan test tests/Ticketing/Acceptance
```

### Load Testing with k6

Simulate 1,000 concurrent users competing for 100 tickets:

```bash
# Seed stress data first
docker compose exec laravel php artisan db:seed --class=StressTestSeeder

# Run k6 (via Docker)
docker run --rm -i -e BASE_URL=http://host.docker.internal grafana/k6 run - < tests/Load/k6/purchase_stress_test.js
```

**Success Criteria:**
- Exactly 100 sales in database
- 900 requests fail with `409 Conflict` or `422` (expected)
- Zero `500 Internal Server Error`

---

## Project Structure

```
src/
├── Shared/                          # Shared Kernel
│   ├── Domain/Audit/AuditLogger.php
│   └── Infrastructure/
│       ├── Audit/                   # Composite + Eloquent + File loggers
│       ├── Middleware/              # CorrelationId, SecurityHeaders
│       └── Persistence/Models/      # AuditLogModel
│
├── Security/                        # Authentication context
│   ├── Application/UseCases/LoginUseCase.php
│   ├── Domain/Ports/Authenticator.php
│   └── Infrastructure/
│       ├── Auth/SanctumAuthenticator.php
│       ├── Controllers/AuthController.php
│       └── Middleware/EnsureRole.php
│
├── Ticketing/                       # Core domain context
│   ├── Domain/
│   │   ├── Model/                   # Ticket, Seat, Reservation, Event, Season
│   │   ├── ValueObjects/            # Money, SeatId
│   │   ├── Events/                  # TicketSold, ReservationPaid
│   │   ├── Exceptions/              # SeatAlreadySoldException, etc.
│   │   ├── Repositories/            # Repository interfaces
│   │   └── Ports/PaymentGateway.php
│   │
│   ├── Application/
│   │   ├── UseCases/                # PurchaseTicketUseCase, ProcessTicketPaymentUseCase
│   │   ├── Queries/                 # GetEventSeatsQuery, GetEventStatsQuery
│   │   ├── DTOs/                    # PurchaseTicketRequestDTO
│   │   └── Ports/                   # StockManager, IdempotencyStore, AsyncDispatcher
│   │
│   └── Infrastructure/
│       ├── Controllers/             # HTTP entry points
│       ├── Persistence/             # Eloquent + Redis implementations
│       ├── Payment/                 # StripeGateway, FakeGateway, CircuitBreaker
│       ├── Jobs/                    # ProcessTicketPayment
│       ├── Listeners/               # InvalidateSeatsCacheOnTicketSold
│       └── Console/                 # CleanupExpiredReservations
│
└── Reports/                         # Admin report downloads
    ├── Application/DownloadReportUseCase.php
    └── Infrastructure/Storage/LaravelReportStorage.php

tests/
├── Ticketing/
│   ├── Unit/Domain/                 # Entity & Value Object tests
│   ├── Integration/                 # Repository & Job tests
│   └── Acceptance/                  # HTTP flow tests
├── Security/Acceptance/             # Auth tests
└── Load/k6/                         # Stress tests
```

---

## Contribution Workflow

- All changes via feature branches + Pull Requests. No direct pushes to `main`.
- Conventional Commits: `type(scope): description`
- Allowed types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
- CI runs lint, static analysis, and full test suite in Docker

---

## License

MIT
