# Betedesta Bar & Restaurant

POS and operations system for Betedesta, an Ethiopian bar and restaurant.

The backend (`/backend`) is Laravel 12 + Sanctum. The frontend (`/frontend`) is Nuxt 4 (SPA) + Vue 3 + Tailwind CSS + Pinia.

## Requirements

- PHP 8.3+ with `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `openssl`, `bcmath`
- Composer 2
- Node 20+
- SQLite for local development (MySQL 8 is supported by the same migrations)

## Backend setup

```bash
cd backend
copy .env.example .env   # Windows
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

The API listens on `http://localhost:8000/api/v1`.

Queue workers are optional for the MVP. Events are dispatched in-process (`QUEUE_CONNECTION=sync` is fine).

## Frontend setup

```bash
cd frontend
copy .env.example .env
npm install
npm run dev
```

The UI listens on `http://localhost:3000` and calls `NUXT_PUBLIC_API_BASE` (default `http://localhost:8000/api/v1`).

## Tests

```bash
cd backend
php artisan test
```

PHPUnit uses an in-memory SQLite database.

## Deploy backend to Render

Docker + PostgreSQL. Step-by-step: [docs/DEPLOY_RENDER.md](docs/DEPLOY_RENDER.md).

Quick path: push the repo → Render **PostgreSQL** → **Web Service** with Root Directory `backend`, Runtime **Docker**, set `APP_KEY`, `DB_CONNECTION=pgsql`, `DB_URL` (Internal Database URL), `FRONTEND_URL`, then deploy. Health check: `/up`.

## Dev credentials

All seeded accounts use password `Password123!`.

| Role | Email |
| --- | --- |
| Admin | admin@omniva.test |
| Manager | manager@omniva.test |
| Cashier | cashier@omniva.test |
| Waiter | waiter@omniva.test |
| Kitchen | kitchen@omniva.test |

## Architecture

- **Auth:** Laravel Sanctum personal access tokens (Bearer). No cookie SPA session.
- **Authorization:** `roles.slug` maps to permissions in `backend/config/permissions.php`. Admin bypasses via `Gate::before`. Every API endpoint re-checks permissions. The Nuxt sidebar and route middleware only hide UI.
- **Money:** `decimal(15,2)` columns and `App\Support\Money` (bcmath). The frontend never supplies totals.
- **Order numbers:** `ORD-YYYYMMDD-0001` from a locked `sequences` row, unique index as backstop.
- **Inventory deduction:** on order completion, via `inventory_deducted_at` conditional update plus a unique inventory-transaction reference. Refunds do not restock automatically.
- **Kitchen:** polling every 5s. Status transitions are validated (`new → preparing → ready`).
- **Branches:** Admin/owner can open more locations under the same restaurant, copy the menu, and switch the working branch from the top bar or Settings. Staff stay on the branch they are assigned to. Tables, orders, kitchen, inventory, and reports always follow the working branch.

## Business rules

1. Dine-in orders require a table. Occupied tables cannot open a second order.
2. Prices and tax are calculated on the backend from menu snapshots + branch tax rate.
3. Submitted item removals and quantity reductions are audit-logged.
4. Payments cannot exceed `balance_due`. Split tenders are allowed. Overpayment is rejected.
5. A dine-in order completes when it is **served** and the balance is zero. Takeaway may complete on full payment.
6. Invalid status jumps (e.g. `completed → preparing`) are rejected by `OrderStateMachine`.
7. Menu price changes do not rewrite historical order line snapshots.
8. The owner operates one branch at a time. Opening a second location does not mix its tables, orders, or stock with the first.

## Primary workflow

Waiter signs in → selects an available table → adds items (variants/modifiers/notes) → sends to kitchen → kitchen prepares and marks ready → waiter serves → cashier takes payment (one or more methods) → receipt prints → table frees → ingredients deduct once → sale appears on the dashboard and reports.
