# Ticketing System (High Concurrency & Stress Testing)

## 🧠 AI Role & Project Goal
This project is a high-concurrency ticketing system designed to handle massive traffic spikes (e.g., "Sold Out" scenarios).
The main goal is to ensure **Data Consistency** and **Integrity** under extreme load (1,000 concurrent users).

## 🛠 Tech Stack
- **Language:** PHP 8.4 (Strict Types, Readonly classes, Enums).
- **Framework:** Laravel 12 (Used as 'glue code' and delivery mechanism only).
- **Database:** MySQL 8.0 (InnoDB, READ COMMITTED).
- **Cache/Locking:** Redis (Atomic operations, Lua scripts, Distributed Locks).
- **Testing:**
    - **Unit/Feature:** PHPUnit / Pest (Strict TDD).
    - **Load/Stress:** k6 (JavaScript).

## 🏗 Architecture (Hexagonal + DDD)
The project follows strict Hexagonal Architecture principles.

### Directory Structure
```text
src/Ticketing/
├── Domain/              # Pure business logic (Inner Hexagon)
│   ├── Model/           # Entities (Seat, Event)
│   ├── ValueObjects/    # Value Objects (Money, SeatId)
│   ├── Ports/           # Interfaces (TicketRepository, StockManager)
│   ├── Events/          # Domain Events (TicketSold)
│   └── Exceptions/      # Domain Exceptions (SeatAlreadySoldException)
│
├── Application/         # Application Logic (Coordinating Hexagon)
│   ├── UseCases/        # Command Handlers (PurchaseTicketUseCase)
│   └── DTOs/            # Data Transfer Objects
│
└── Infrastructure/      # Framework & I/O (Adapters)
    ├── Persistence/     # Eloquent & Redis Implementations
    ├── Http/            # Controllers
    └── Console/         # Commands
```

### Dependency Rules
1. **Domain** depends on NOTHING.
2. **Application** depends ONLY on Domain.
3. **Infrastructure** depends on Application and Domain.

## ⚡ Technical Challenges & Coding Rules

### 1. Concurrency Handling (CRITICAL)
- **Redis Atomic Locks:** First line of defense. Checks and decrements stock atomically using Lua scripts.
- **DB Transaction & Pessimistic Locking:** `SELECT ... FOR UPDATE` ensures row-level locking in MySQL.
- **Idempotency:** `Idempotency-Key` header support to prevent double charges.

### 2. Domain Validations
- Business rules are enforced in Domain Entities and Value Objects, not in Controllers.
- Example: `Seat` ensures it cannot be reserved if already sold.

## 🧪 Testing Strategy

### 1. Unit & Feature Tests
We achieve 100% coverage in the Domain layer and test the full purchase flow via Feature tests.

**Run Tests (Docker):**
```bash
# Run all tests
docker exec ticketing-laravel-1 php artisan test

# Run only Ticketing tests
docker exec ticketing-laravel-1 php artisan test tests/Ticketing
```

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

**Run Stress Test (Docker):**

If you have k6 installed locally:
```bash
k6 run tests/Load/k6/purchase_stress_test.js
```

If you want to run k6 via Docker (recommended):
```bash
# Linux
docker run --rm -i --network host grafana/k6 run - < tests/Load/k6/purchase_stress_test.js

# Windows / Mac (accessing host via host.docker.internal)
docker run --rm -i -e BASE_URL=http://host.docker.internal grafana/k6 run - < tests/Load/k6/purchase_stress_test.js
```

## 🚀 Getting Started

### Prerequisites
- Docker & Docker Compose

### Installation
1. Start the containers:
   ```bash
   ./vendor/bin/sail up -d
   ```
   Or directly with docker-compose:
   ```bash
   docker compose up -d
   ```

2. Run migrations (and seed if necessary):
   ```bash
   docker exec ticketing-laravel-1 php artisan migrate
   ```

3. (Optional) Access the shell:
   ```bash
   docker exec -it ticketing-laravel-1 bash
   ```

## 📝 Implementation Details

### Purchase Flow
1. **Request:** `POST /api/tickets/purchase` with `Idempotency-Key`.
2. **Idempotency Check:** Redis checks if key exists.
3. **Stock Check (Redis):** Atomic decrement of stock. Fails fast if sold out.
4. **DB Transaction:**
   - Lock Row: `SELECT ... FOR UPDATE` on `seats` table.
   - Domain Guard: `$seat->reserve($user)`.
   - Save: Commit transaction.
   - Event: Dispatch `TicketSold`.
5. **Failure Compensation:** If DB fails, revert Redis stock decrement.

### Key Files
- `src/Ticketing/Domain/Model/Seat.php`: Aggregate Root with business logic.
- `src/Ticketing/Application/UseCases/PurchaseTicketUseCase.php`: Orchestrator.
- `src/Ticketing/Infrastructure/Persistence/RedisStockManager.php`: Lua script for atomic stock.
