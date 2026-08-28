# Ticketing System — High-Concurrency Ticket Reservation

[![CI](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/ci.yml/badge.svg)](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/ci.yml)
[![Code Quality](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/CristianLopez29/ticketing-system-demo/actions/workflows/static-analysis.yml)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8.4/en.php)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHPStan level 9](https://img.shields.io/badge/PHPStan-level%209-2a5ea7)](phpstan.neon)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

The first two badges are live status from this repository's **GitHub Actions** workflows:
**CI** runs the full PHPUnit suite against MySQL 8 + Redis 7, and **Code Quality** runs
Larastan level 9, Pint and a Composer CVE audit as three independent jobs. The rest are
static version and licence labels.

A ticket reservation and purchasing system built to demonstrate **Hexagonal Architecture**, **Domain-Driven Design**, and **high-concurrency data integrity** under extreme load.

> **Portfolio Focus:** This project showcases how to prevent race conditions when 1,000 users simultaneously compete for 100 tickets.

---

## Proof Under Load

The claim above is a measurement, not a design intention. Below is the unedited output of
[`tests/Load/k6/purchase_stress_test.js`](tests/Load/k6/purchase_stress_test.js):
**1,000 distinct authenticated buyers, 3,691 purchase attempts, 100 seats.**

> **Measured locally, not on the production VPS.** The run below was executed on a Windows 10
> workstation (12 logical CPUs, 8 GB allocated to Docker Desktop 29.7.2), with k6 v2.0.0 driving
> the Laravel Sail container from inside the compose network. The deployed instance runs a
> different stack entirely — Nginx and PHP-FPM, see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) —
> on hardware these numbers say nothing about. **Read the integrity column, not the latency one.**

```text
     ✓ seat was sold (202) or contention was rejected (409/422)
     ✓ no server error

     checks.........................: 100.00% ✓ 7382      ✗ 0
     data_received..................: 2.8 MB  74 kB/s
     data_sent......................: 1.2 MB  31 kB/s
     http_req_blocked...............: avg=383.11µs min=86.4µs med=263.79µs max=18.28ms p(90)=555.69µs p(95)=884µs
     http_req_connecting............: avg=272.32µs min=60.6µs med=171.8µs  max=17.96ms p(90)=403.6µs  p(95)=666.65µs
     http_req_duration..............: avg=7.96s    min=2.64s  med=8.67s    max=10.33s  p(90)=10.13s   p(95)=10.23s
       { expected_response:true }...: avg=7.96s    min=2.64s  med=8.67s    max=10.33s  p(90)=10.13s   p(95)=10.23s
   ✓ http_req_failed................: 0.00%   ✓ 0         ✗ 3691
     http_req_receiving.............: avg=1.58ms   min=67.8µs med=1.22ms   max=17.64ms p(90)=2.86ms   p(95)=3.71ms
     http_req_sending...............: avg=84.15µs  min=10.9µs med=64.9µs   max=2.65ms  p(90)=117.5µs  p(95)=170.45µs
     http_req_tls_handshaking.......: avg=0s       min=0s     med=0s       max=0s      p(90)=0s       p(95)=0s
     http_req_waiting...............: avg=7.95s    min=2.64s  med=8.67s    max=10.33s  p(90)=10.13s   p(95)=10.23s
     http_reqs......................: 3691    95.628042/s
     iteration_duration.............: avg=7.96s    min=2.65s  med=8.67s    max=10.33s  p(90)=10.13s   p(95)=10.23s
     iterations.....................: 3691    95.628042/s
     purchase_accepted..............: 100     2.590844/s
     purchase_rejected..............: 3591    93.037198/s
   ✓ purchase_server_errors.........: 0       0/s
   ✓ purchase_throttled.............: 0       0/s
   ✓ purchase_unexpected............: 0       0/s
     vus............................: 134     min=64      max=1000
     vus_max........................: 1000    min=1000    max=1000
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
redis_queue_pending  = 0
failed_jobs          = 0
pending_refunds      = 0
```

| Criterion | Expected | Measured |
|-----------|----------|----------|
| Seats sold | exactly 100 | **100** |
| Seats sold twice | 0 | **0** |
| `500 Internal Server Error` | 0 | **0** |
| Losing buyers rejected with 409/422 | all | **3,591 / 3,591** |
| Redis stock counter at the end | 0 | **0** |
| Payment saga failures / stranded refunds | 0 | **0 / 0** |

**About the latency.** `p(95) = 10.23s` is the cost of 1,000 virtual users queueing against
PHP's built-in development server (`artisan serve`, 10 workers) inside Docker Desktop, plus one
structured access-log write per request. It is a property of that test rig, not of the
application, and it is reported unmodified because the number this project is about is the
integrity column. Raw artifacts:
[`k6-summary.txt`](docs/load-test/k6-summary.txt),
[`k6-summary.json`](docs/load-test/k6-summary.json),
[`db-verification.txt`](docs/load-test/db-verification.txt).

[Reproduce this run →](#load-testing-with-k6)

---

## Table of Contents

1. [Proof Under Load](#proof-under-load)
2. [Quick Start](#quick-start)
3. [Trying the API](#trying-the-api)
4. [Architecture Overview](#architecture-overview)
5. [Core Flow: Purchasing a Ticket](#core-flow-purchasing-a-ticket)
6. [Tech Stack](#tech-stack)
7. [API Endpoints](#api-endpoints)
8. [Configuration](#configuration)
9. [Testing](#testing)
10. [Deployment](#deployment)
11. [Project Structure](#project-structure)
12. [Contribution Workflow](#contribution-workflow)
13. [License](#license)

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

## Trying the API

Interactive OpenAPI 3 documentation is served at **`/api/documentation`** — every endpoint is
callable from the browser with "Try it out".

| Environment | Access |
|---|---|
| Local (`APP_ENV=local`) | Open, no credentials |
| Anywhere else | HTTP **Basic auth** — `DOCS_AUTH_USERNAME` / `DOCS_AUTH_PASSWORD` |

Basic auth rather than a bearer token for a deliberate reason: a browser cannot attach a
bearer token to a plain navigation, so guarding the UI with `auth:sanctum` made it permanently
unreachable. With `L5_SWAGGER_PROTECT=true` and no password set the route answers **503** — it
never falls open.

To exercise an authenticated endpoint:

```bash
# 1. Get a token
curl -X POST http://localhost/api/login   -H 'Content-Type: application/json'   -d '{"email":"admin@example.com","password":"password"}'

# 2. Use it — note the Idempotency-Key, which is required and must be a UUID v4
curl -X POST http://localhost/api/tickets/purchase   -H 'Authorization: Bearer <token>'   -H 'Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000'   -H 'Content-Type: application/json'   -d '{"event_id":1,"seat_id":1}'
```

In Swagger UI, paste `Bearer <token>` into the **Authorize** dialog. Every response carries an
`X-Correlation-ID` header that joins the application and access logs for that request.

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
| Observability | Monolog JSON channels (app + access), Sentry (optional) |
| CI | GitHub Actions (test suite + PHPStan) |

There is no frontend: this is a JSON API, with no `package.json` and no build step.

---

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Service index: name, status and links to the docs and probes |
| POST | `/api/login` | Authenticate (5/min per email+IP, 30/min per IP) |
| GET | `/api/events/{id}/seats` | List available seats, cursor pagination (60/min per IP) |
| GET | `/api/health` | Liveness probe (60/min per IP) |
| GET | `/api/readiness` | Readiness probe — **503** when MySQL or Redis is down (60/min per IP) |
| GET | `/api/documentation` | Swagger UI (Basic auth outside local) |

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
| GET | `/api/reports/download` | Download report file (30/min) |
| POST | `/api/users/{id}/tokens/revoke-all` | Revoke all user tokens |

The health probes are deliberately unauthenticated so orchestrators can call them; restrict
them at the reverse proxy if you need to — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

Rate limiting lives in two places on purpose. The application throttles express business
limits (per user, per email) that a web server cannot know about; the Nginx `limit_req` zone in
`docker/production/nginx.conf` catches volumetric abuse before PHP is reached, and keeps
answering when PHP-FPM has no free workers left.

---

## Configuration

`.env.example` is the local and CI template. **For a public deployment start from
[`.env.production.example`](.env.production.example)** — it flips the settings that matter and
carries no development defaults.

Variables introduced beyond a stock Laravel install:

| Variable | Default | Purpose |
|---|---|---|
| `TRUSTED_PROXIES` | `127.0.0.1,::1` | Proxy IPs, or `*`. **Getting this wrong silently disables every per-IP rate limit**, because `$request->ip()` then returns the proxy address for all traffic. |
| `CORS_ALLOWED_ORIGINS` | `*` | Comma-separated origins. `*` is defensible only while authentication stays bearer-token only (`supports_credentials` is false). |
| `DOCS_AUTH_USERNAME` | `docs` | Basic-auth user for `/api/documentation`. |
| `DOCS_AUTH_PASSWORD` | *(unset)* | Basic-auth password. Unset with protection on ⇒ the docs route answers 503. |
| `L5_SWAGGER_PROTECT` | `APP_ENV !== 'local'` | Forces the docs behind Basic auth. |
| `LOG_CHANNEL` | `stack` | Use `daily_json` in production: JSON lines, rotated daily. |
| `LOG_DAILY_DAYS` / `LOG_ACCESS_DAYS` | `14` | Retention for the application and access logs. |
| `SENTRY_LARAVEL_DSN` | *(empty)* | Error monitoring. Empty disables Sentry entirely. |
| `SENTRY_TRACES_SAMPLE_RATE` | `0.1` | Performance-trace sampling. |
| `PAYMENT_GATEWAY_DRIVER` | `fake` | `fake` or `stripe`. |
| `SEASON_TICKET_DISCOUNT` | `20` | Season-ticket discount, percent. |

### Logging

Two JSON channels, deliberately separate files:

| Channel | File | Contents |
|---|---|---|
| `daily_json` | `storage/logs/app-YYYY-MM-DD.log` | Application events, level `info` and above |
| `access` | `storage/logs/access-YYYY-MM-DD.log` | One line per request: method, path, status, duration, IP, user id |

Both rotate daily and carry the request's `correlation_id`, which is also returned to the
client as `X-Correlation-ID`. Request bodies are never logged — `/api/login` carries passwords.

Errors reach Sentry only when `SENTRY_LARAVEL_DSN` is set. Expected domain outcomes
(sold out, seat taken, duplicate request, auth failure) are excluded in
[`bootstrap/app.php`](bootstrap/app.php): a single contended sale would otherwise produce one
event per losing buyer and bury the real faults.

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

Run manually with **k6 v2.0.0**, never as part of `php artisan test` and **never against the
deployed VPS** — it is a deliberate denial-of-service against your own machine.

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

## Deployment

Full procedure: **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.

The production stack is [`compose.prod.yaml`](compose.prod.yaml) plus
[`docker/production/`](docker/production/) — PHP-FPM 8.4, Nginx, a queue worker and a
scheduler, with MySQL and Redis private to the stack. It publishes **no ports**: the only way
in is a shared reverse proxy on a shared Docker network, so the host can run other
applications alongside it.

```bash
cp .env.production.example .env      # fill every <placeholder>
docker network create proxy || true
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml exec app php artisan key:generate --force
docker compose -f compose.prod.yaml exec app php artisan migrate --force
```

`compose.yaml` is the Laravel Sail **development** runtime and is not used in production.

Both background processes are mandatory and run as their own containers: without `worker`
payment never completes, and without `scheduler` expired reservations never release their
seats.

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
│       ├── Middleware/              # CorrelationId, RequestLogger, SecurityHeaders,
│       │                            #   DocsBasicAuth, SwaggerUiCsp
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
├── Shared/Acceptance/               # Root index, health/readiness, docs Basic auth
├── Integration/                     # Redis idempotency
└── Load/k6/                         # Stress tests (not a PHPUnit suite)

docker/production/                   # Production image, Nginx and PHP config
docs/DEPLOYMENT.md                   # VPS procedure
docs/load-test/                      # Committed k6 artifacts backing the README numbers
```

---

## Contribution Workflow

- All changes via feature branches + Pull Requests. No direct pushes to `main`.
- Conventional Commits: `type(scope): description`
- Allowed types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
- CI (GitHub Actions) runs the full PHPUnit suite against MySQL 8 + Redis 7, plus three
  quality jobs: PHPStan level 9, `pint --test` and `composer audit`. Run
  `./vendor/bin/pint` before committing so the style job stays green.
- The audit job passes `--abandoned=report`: `doctrine/annotations` is abandoned and is a
  transitive dependency of the Swagger generator. It is not a vulnerability and does not
  gate a merge.

---

## License

Released under the [MIT License](LICENSE).
