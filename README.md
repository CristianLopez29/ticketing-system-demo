# Ticketing System — High-Concurrency Ticket Reservation

[![CI](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/ci.yml/badge.svg)](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/ci.yml)
[![Static Analysis](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/static-analysis.yml)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8.4/en.php)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHPStan level 9](https://img.shields.io/badge/PHPStan-level%209-2a5ea7)](phpstan.neon)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A ticket reservation and purchasing system built to demonstrate **Hexagonal Architecture**, **Domain-Driven Design**, and **high-concurrency data integrity** under extreme load.

> **Portfolio Focus:** This project showcases how to prevent race conditions when 1,000 users simultaneously compete for 100 tickets.

---

## Proof Under Load

The claim above is a measurement, not a design intention. Below is the unedited output of
[`tests/Load/k6/purchase_stress_test.js`](tests/Load/k6/purchase_stress_test.js):
**1,000 distinct authenticated buyers, 5,057 purchase attempts, 100 seats.**

```text
     ✓ seat was sold (202) or contention was rejected (409/422)
     ✓ no server error

     checks.........................: 100.00% ✓ 10114      ✗ 0
     data_received..................: 3.7 MB  98 kB/s
     data_sent......................: 1.6 MB  44 kB/s
     http_req_blocked...............: avg=292.64µs min=55.1µs   med=244.7µs  max=5.34ms p(90)=417.08µs p(95)=549.21µs
     http_req_connecting............: avg=201.11µs min=37.79µs  med=157.29µs max=5.24ms p(90)=288.88µs p(95)=414.09µs
     http_req_duration..............: avg=5.63s    min=932.35ms med=6.92s    max=7.27s  p(90)=7.16s    p(95)=7.19s
       { expected_response:true }...: avg=5.63s    min=932.35ms med=6.92s    max=7.27s  p(90)=7.16s    p(95)=7.19s
   ✓ http_req_failed................: 0.00%   ✓ 0          ✗ 5057
     http_req_receiving.............: avg=1.28ms   min=65.5µs   med=1.06ms   max=9.14ms p(90)=2.05ms   p(95)=2.64ms
     http_req_sending...............: avg=75.06µs  min=13.89µs  med=63.5µs   max=1.69ms p(90)=105µs    p(95)=139.8µs
     http_req_tls_handshaking.......: avg=0s       min=0s       med=0s       max=0s     p(90)=0s       p(95)=0s
     http_req_waiting...............: avg=5.63s    min=931.16ms med=6.92s    max=7.27s  p(90)=7.16s    p(95)=7.19s
     http_reqs......................: 5057    135.91649/s
     iteration_duration.............: avg=5.63s    min=933.01ms med=6.92s    max=7.27s  p(90)=7.16s    p(95)=7.19s
     iterations.....................: 5057    135.91649/s
     purchase_accepted..............: 100     2.68769/s
     purchase_rejected..............: 4957    133.228799/s
   ✓ purchase_server_errors.........: 0       0/s
   ✓ purchase_throttled.............: 0       0/s
   ✓ purchase_unexpected............: 0       0/s
     vus............................: 85      min=66       max=1000
     vus_max........................: 1000    min=1000     max=1000
```

HTTP status codes alone do not prove integrity — the database does. Immediately after the
run, with the payment saga worker drained:

```text
seats_total          = 100
seats_reserved       = 100
reservations         = 100
double_sold_seats    = 0
tickets_issued       = 100
reservations_paid    = 100
redis_stock          = '0'
jobs_pending         = 0
failed_jobs          = 0
pending_refunds      = 0
```

| Criterion | Expected | Measured |
|-----------|----------|----------|
| Seats sold | exactly 100 | **100** |
| Seats sold twice | 0 | **0** |
| `500 Internal Server Error` | 0 | **0** |
| Losing buyers rejected with 409/422 | all | **4,957 / 4,957** |
| Redis stock counter at the end | 0 | **0** |
| Payment saga failures / stranded refunds | 0 | **0 / 0** |

**About the latency.** `p(95) = 7.19s` is the cost of 1,000 virtual users queueing against
PHP's built-in development server (`artisan serve`, 10 workers) inside Docker Desktop — it is
not a production figure. It is reported unmodified because the number this project is about is
the integrity column, not the throughput one. Raw artifacts:
[`k6-summary.txt`](docs/load-test/k6-summary.txt),
[`k6-summary.json`](docs/load-test/k6-summary.json),
[`db-verification.txt`](docs/load-test/db-verification.txt).

[Reproduce this run →](#load-testing-with-k6)

---

## Table of Contents

1. [Proof Under Load](#proof-under-load)
2. [Quick Start](#quick-start)
3. [Architecture Overview](#architecture-overview)
4. [Core Flow: Purchasing a Ticket](#core-flow-purchasing-a-ticket)
5. [Tech Stack](#tech-stack)
6. [API Endpoints](#api-endpoints)
7. [Testing](#testing)
8. [Project Structure](#project-structure)
9. [Contribution Workflow](#contribution-workflow)
10. [License](#license)

---

## Quick Start

### Prerequisites
- Docker & Docker Compose

### Installation

```bash
# 1. Start containers (laravel, mysql, redis, mailpit)
docker compose up -d

# 2. Install dependencies & generate key
docker compose exec laravel composer install
docker compose exec laravel php artisan key:generate

# 3. Run migrations
docker compose exec laravel php artisan migrate

# 4. Generate API docs
docker compose exec laravel php artisan l5-swagger:generate
```

The API listens on `http://localhost:${APP_PORT:-80}` and Swagger UI on `/api/documentation`.

Two background processes complete the system — **without them the app looks broken, not slow**:

```bash
# Required: payment is a saga. No worker, no ticket — reservations stay pending_payment.
docker compose exec laravel php artisan queue:work

# Required: releases seats whose reservation expired (cleanup runs every minute).
docker compose exec laravel php artisan schedule:work
```

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

Four contexts under the `Src\` namespace, with **zero cross-context imports** — they
communicate through domain events and the shared kernel only.

| Context | Responsibility |
|---------|---------------|
| `Ticketing` | Events, seats, reservations, tickets, season tickets, stock, payments |
| `Security` | Authentication via Sanctum, rate limiting, role gate |
| `Reports` | Admin report downloads |
| `Shared` | Audit logging, health checks, correlation id, base classes |

---

## Core Flow: Purchasing a Ticket

The [`PurchaseTicketUseCase`](src/Ticketing/Application/UseCases/PurchaseTicketUseCase.php) is the star of the system. It guarantees that **exactly one user gets each seat** even under extreme concurrency.

```
POST /api/tickets/purchase
Header: Idempotency-Key: <uuid-v4>
Body:   { "event_id": 1, "seat_id": 42 }
→ 202 Accepted { "reservation_id": "…" }
```

### Phase 1 — synchronous, returns `202 Accepted`

| Step | Layer | What Happens |
|------|-------|-------------|
| 1 | Controller | Validates `Idempotency-Key` (UUID v4) and input |
| 2 | Application | Checks the idempotency store (Redis) — duplicate? Return the previous result |
| 3 | Application | Atomically decrements stock in Redis via Lua (fast fail if sold out) |
| 4 | Application | Opens a DB transaction and takes `SELECT ... FOR UPDATE` on the seat row |
| 5 | Domain | `$seat->isAvailable()` → `$seat->reserve($userId)` — the domain decides, not the query |
| 6 | Domain | `Reservation::create(...)` — new aggregate, `pending_payment` |
| 7 | Application | Commits the transaction, releasing the row lock |
| 8 | Application | Dispatches the async payment job (Saga pattern) |
| 9 | Application | Marks the idempotency key as completed with the reservation id |

Any throw after step 3 reverts the Redis counter and forgets the idempotency key, so the
buyer can retry without leaking stock.

### Phase 2 — asynchronous, on the queue worker

| Step | Layer | What Happens |
|------|-------|-------------|
| 10 | Application | `ProcessTicketPaymentUseCase` charges through `PaymentGateway` (circuit-breaker wrapped) |
| 11 | Domain | `Reservation::markAsPaid()` and `Ticket::issue(...)`, which records `TicketSold` |
| 12 | Infrastructure | `InvalidateSeatsCacheOnTicketSold` flushes the tagged seats read model |
| — | Application | On failure: seat released, `PendingRefund` recorded, buyer notified |

### Concurrency Safeguards

- **Redis atomic stock counter** — Lua `GET`-check-`DECR`; first barrier, keeps sold-out traffic off MySQL
- **Pessimistic DB locking** — `lockForUpdate()` guarantees row-level isolation
- **Idempotency** — the same `Idempotency-Key` always returns the same result, never double-charges
- **Circuit Breaker** — [`RedisCircuitBreaker`](src/Ticketing/Infrastructure/Payment/RedisCircuitBreaker.php) prevents cascading payment failures
- **Compensation** — every early exit after the stock decrement restores the counter

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.4 (strict types, readonly classes, enums) |
| Framework | Laravel 12 (delivery mechanism only) |
| Database | MySQL 8.0 (InnoDB, pessimistic row locks) |
| Cache/Locking | Redis (atomic Lua scripts, distributed locks, idempotency store) |
| Auth | Laravel Sanctum (bearer tokens) |
| API Docs | L5-Swagger / OpenAPI 3 |
| Testing | PHPUnit 11 + Mockery, k6 (load/stress) |
| Static analysis | Larastan (PHPStan) level 9, Laravel Pint |
| CI | GitHub Actions (test suite + PHPStan) |

There is no frontend: this is a JSON API, with no `package.json` and no build step.

---

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Authenticate (5/min per email+IP, 30/min per IP) |
| GET | `/api/events/{id}/seats` | List available seats (cursor pagination) |
| GET | `/api/health` | Health probe |
| GET | `/api/readiness` | Readiness probe (DB + cache) |

### Authenticated
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/tickets/purchase` | Purchase single ticket (idempotent, 60/min) |
| POST | `/api/season-tickets/purchase` | Purchase season ticket |
| POST | `/api/season-tickets/{id}/pay` | Pay pending season ticket |
| POST | `/api/logout` | Revoke current token |
| POST | `/api/refresh-token` | Rotate token |

### Admin Only (`role:admin`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/events/{id}/stats` | Event sales statistics (10/min) |
| GET | `/api/reports/download` | Download report file |
| POST | `/api/users/{id}/tokens/revoke-all` | Revoke all user tokens |

The health probes are deliberately unauthenticated so orchestrators can call them; protect
them at the network layer.

---

## Testing

### Test Pyramid

```
      /\
     /  \     Acceptance (HTTP end-to-end, RefreshDatabase)
    /____\
   /      \   Integration (real MySQL, real Redis, real queue worker)
  /________\
 /          \ Unit (Domain logic, no framework)
/____________\
```

### Running Tests

There is **no SQLite in-memory database** — the suites talk to the real MySQL `testing`
schema and to real Redis, on hostnames that only resolve inside the compose network. Run
them through the container:

```bash
# All suites
docker compose exec laravel php artisan test

# By suite
docker compose exec laravel php artisan test --testsuite=Ticketing
docker compose exec laravel php artisan test --testsuite=Security
docker compose exec laravel php artisan test --testsuite=Integration

# Static analysis and formatting
docker compose exec laravel ./vendor/bin/phpstan analyse --memory-limit=2G
docker compose exec laravel ./vendor/bin/pint --test
```

### Load Testing with k6

`StressTestSeeder` provisions the sold-out scenario: one event, 100 seats, and **1,000
buyers each with their own bearer token** — a single shared token would hit the per-user
`throttle:60,1` limit and the run would measure the rate limiter instead of the seat lock.

```bash
# 1. Seed 100 seats + 1,000 buyer tokens (written to tests/Load/k6/stress-tokens.json)
docker compose exec laravel php artisan db:seed --class=StressTestSeeder

# 2. Run the payment saga worker in a second shell
docker compose exec laravel php artisan queue:work --tries=1

# 3. Fire the herd. Running inside the compose network keeps Docker's port-forwarding
#    NAT out of the measurement.
docker run --rm --network ticketing_sail \
  -e BASE_URL=http://laravel \
  -e K6_REPORT_DIR=/reports \
  -v "$PWD/tests/Load/k6:/scripts:ro" \
  -v "$PWD/docs/load-test:/reports" \
  grafana/k6 run /scripts/purchase_stress_test.js
```

The script writes `docs/load-test/k6-summary.{txt,json}` on every run, so the numbers in
[Proof Under Load](#proof-under-load) are regenerated artifacts rather than screenshots.

**Then verify the database — the HTTP result is not the proof:**

```sql
SELECT COUNT(*) FROM seats WHERE reserved_by_user_id IS NOT NULL;  -- must be 100
SELECT COUNT(*) FROM tickets;                                      -- must be 100
SELECT COUNT(*) FROM (
    SELECT seat_id FROM reservations GROUP BY seat_id HAVING COUNT(*) > 1
) AS double_sold;                                                  -- must be 0
SELECT COUNT(*) FROM failed_jobs;                                  -- must be 0
```

A `500` in the k6 output means an unhandled deadlock. Treat it as a bug in the concurrency
contract, not as load noise.

---

## Project Structure

```
src/
├── Shared/                          # Shared Kernel
│   ├── Domain/
│   │   ├── AggregateRoot.php        # Records domain events
│   │   ├── Audit/AuditLogger.php
│   │   └── Services/UuidGenerator.php
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
│   │   ├── Model/                   # Event, Seat, Reservation, Ticket, Season, SeasonTicket
│   │   ├── ValueObjects/            # Money (integer cents), SeatId
│   │   ├── Enums/                   # ReservationStatus
│   │   ├── Events/                  # TicketSold, ReservationPaid, ReservationCancelled
│   │   ├── Exceptions/              # SeatAlreadySoldException, etc.
│   │   ├── Repositories/            # Persistence ports
│   │   └── Ports/PaymentGateway.php
│   │
│   ├── Application/
│   │   ├── UseCases/                # PurchaseTicketUseCase, ProcessTicketPaymentUseCase
│   │   ├── Queries/                 # GetEventSeatsQuery, GetEventStatsQuery (CQRS read side)
│   │   ├── DTOs/                    # PurchaseTicketRequestDTO
│   │   └── Ports/                   # StockManager, IdempotencyStore, TransactionManager, AsyncDispatcher
│   │
│   └── Infrastructure/
│       ├── Controllers/             # HTTP entry points
│       ├── Persistence/             # Eloquent + Redis implementations
│       ├── Payment/                 # StripeGateway, FakeGateway, RedisCircuitBreaker
│       ├── Jobs/                    # ProcessTicketPayment
│       ├── Listeners/               # InvalidateSeatsCacheOnTicketSold
│       └── Console/Commands/        # CleanupExpiredReservations
│
└── Reports/                         # Admin report downloads
    ├── Application/UseCases/DownloadReportUseCase.php
    └── Infrastructure/Storage/LaravelReportStorage.php

tests/
├── Ticketing/
│   ├── Unit/                        # Domain entities, Value Objects, listeners, gateways
│   ├── Integration/                 # Repositories, jobs, real queue worker
│   └── Acceptance/                  # HTTP flow tests
├── Security/Acceptance/             # Auth, throttling, token management
├── Integration/                     # Redis idempotency
└── Load/k6/                         # Stress tests (not a PHPUnit suite)

docs/load-test/                      # Committed k6 artifacts backing the README numbers
```

---

## Contribution Workflow

- All changes via feature branches + Pull Requests. No direct pushes to `main`.
- Conventional Commits: `type(scope): description`
- Allowed types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
- CI (GitHub Actions) runs the full PHPUnit suite against MySQL 8 + Redis 7, plus PHPStan
  level 9. Pint is a local gate: run `./vendor/bin/pint` before committing.

---

## License

Released under the [MIT License](LICENSE).
