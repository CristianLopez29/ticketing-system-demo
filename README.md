# Ticketing System (High Concurrency & Stress Testing)

## 🎯 Project Goal
This project is a high-concurrency ticketing system designed to handle massive traffic spikes (e.g., "Sold Out" scenarios).
The main goal is to ensure **Data Consistency** and **Integrity** under extreme load (1,000 concurrent users).

## 🛠 Tech Stack
- **Language:** PHP 8.4 (Strict Types, Readonly classes, Enums).
- **Framework:** Laravel 12 (Used as 'glue code' and delivery mechanism only).
- **Documentation:** L5-Swagger / OpenAPI.
- **Monitoring:** Sentry (Error Tracking).
- **Database:** MySQL 8.0 (InnoDB, READ COMMITTED).
- **Cache/Locking:** Redis (Atomic operations, Lua scripts, Distributed Locks).
- **Testing:**
    - **Unit/Feature:** PHPUnit (Strict TDD).
    - **Load/Stress:** k6 (JavaScript).

## 🏗 Architecture (Hexagonal + DDD)
The project follows strict Hexagonal Architecture principles (Ports & Adapters).

### Directory Structure
```text
src/
├── Shared/                  # Shared Kernel (Audit, Base Classes)
│   ├── Domain/
│   └── Infrastructure/
│
└── Ticketing/               # Ticketing Bounded Context
    ├── Domain/              # Pure business logic (Inner Hexagon)
    │   ├── Model/           # Entities (Reservation, Season, Ticket)
    │   ├── ValueObjects/    # Value Objects (Money, SeatId)
    │   ├── Ports/           # Interfaces (PaymentGateway)
    │   ├── Repositories/    # Repository Interfaces
    │   ├── Events/          # Domain Events (TicketSold)
    │   ├── Exceptions/      # Domain Exceptions
    │
    ├── Application/         # Application Logic (Coordinating Hexagon)
    │   ├── UseCases/        # Command Handlers (PurchaseTicket, PurchaseSeasonTicket)
    │   └── DTOs/            # Data Transfer Objects
    │
    └── Infrastructure/      # Framework & I/O (Adapters)
        ├── Controllers/     # HTTP Controllers
        ├── Console/         # Artisan commands
        ├── Jobs/            # Async Jobs (ProcessTicketPayment)
        ├── Payment/         # Payment adapters (Stripe, Fake)
        └── Persistence/     # Eloquent & Redis Implementations
```

### Dependency Rules
1. **Domain** depends on NOTHING.
2. **Application** depends ONLY on Domain.
3. **Infrastructure** depends on Application and Domain.

## ⚡ Key Features & Implementation Details

### 1. High Concurrency Purchase (Single Ticket)
- **Redis Atomic Locks:** First line of defense. Checks and decrements stock atomically using Lua scripts (`RedisStockManager`).
- **DB Transaction & Pessimistic Locking:** `SELECT ... FOR UPDATE` ensures row-level locking in MySQL.
- **Idempotency:** `Idempotency-Key` header support to prevent double charges.
- **Flow:**
    1. **Request:** `POST /api/tickets/purchase`
    2. **Redis Check:** Fail fast if sold out.
    3. **DB Lock:** Lock seat row.
    4. **Domain Guard:** `$seat->reserve($user)`.
    5. **Commit:** Save and dispatch event.

### 2. Season Tickets (Complex Logic)
- **Renewal Logic:** Supports priority windows where previous season owners have exclusive rights to their seats.
- **Atomic Batch Reservation:** Reserves the same seat across ALL events in a season within a single database transaction.
- **Consistency:** If one event's seat is unavailable, the entire season ticket purchase fails.

### 3. Reservations & Expiration
- **Two-Step Process:** Reserve -> Pay.
- **Status:** `PENDING_PAYMENT` -> `PAID`.
- **Expiration:** Reservations have a TTL (default 5 mins).
- **Cleanup:** `CleanupExpiredReservations` command releases expired seats.

### 4. Audit Logging
- **Shared Kernel:** Centralized `AuditLogger` in `src/Shared` tracks critical actions across the system.

## 🧪 Testing Strategy

### 1. Unit & Feature Tests
We achieve high coverage in the Domain layer and test the full purchase flow via Feature tests.

**Run Tests:**
```bash
# Run all tests
./vendor/bin/sail test

# Run only Ticketing tests
./vendor/bin/sail artisan test tests/Ticketing
```

## 🔀 Contribution Workflow (Branches + PRs + Conventional Commits)
- All changes MUST be implemented via feature branches and merged through Pull Requests (PRs). Direct pushes to `main` are forbidden.
- Split work into independent functional blocks. Each block MUST have its own branch and its own PR, and MUST be reviewable, testable, and mergeable in isolation.
- All commit messages MUST follow Conventional Commits in English using the format: `type(scope): short description`.
- Allowed `type` values: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`.
- PR titles MUST also follow Conventional Commits in English (same format as commits).
- CI MUST run tests inside Docker (using `compose.yaml` / Laravel Sail).
- This practice MUST be verified (automatically and/or manually) during code review before approving any merge.

### 2. Stress Testing (k6)
We verify system resilience with **k6**. The scenario simulates 1,000 concurrent users fighting for 100 tickets.

**Scenario:**
- **Availability:** 100 tickets.
- **Concurrent Users:** 1,000.
- **Duration:** 30 seconds.

**Success Criteria:**
- exactly 100 sales recorded in DB.
- Stock in Redis is 0.
- Remaining requests fail with `409 Conflict` or `422 Unprocessable Entity` (Expected).
- **ZERO** `500 Internal Server Error`.

**Prepare Data:**
```bash
./vendor/bin/sail artisan db:seed --class=StressTestSeeder
```

**Run Stress Test:**

If you have k6 installed locally:
```bash
BASE_URL=http://localhost k6 run tests/Load/k6/purchase_stress_test.js
```

If you want to run k6 via Docker (recommended):
```bash
# Linux / Mac / Git Bash (accessing host via host.docker.internal)
docker run --rm -i -e BASE_URL=http://host.docker.internal grafana/k6 run - < tests/Load/k6/purchase_stress_test.js

# Windows PowerShell
Get-Content tests/Load/k6/purchase_stress_test.js | docker run --rm -i -e BASE_URL=http://host.docker.internal grafana/k6 run -
```

## 🚀 Getting Started

### Prerequisites
- Docker & Docker Compose

### Installation
1. Start the containers:
   ```bash
   ./vendor/bin/sail up -d
   ```
   Or directly with Docker Compose:
   ```bash
   docker compose up -d
   ```

2. Generate app key (first run only):
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

3. Run migrations:
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

4. (Optional) Access the shell:
   ```bash
   ./vendor/bin/sail shell
   ```

5. View API Documentation:
   Visit `/api/documentation` (requires L5-Swagger generation if not already generated).
   ```bash
   ./vendor/bin/sail artisan l5-swagger:generate
   ```
