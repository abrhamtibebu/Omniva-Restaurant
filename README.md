# Betedesta Bar & Restaurant

POS and operations system for Betedesta, an Ethiopian bar and restaurant.

The backend (`/backend`) is Laravel 12 + Sanctum. There are **two** Nuxt 4 SPAs on the same API and database:

| App | Directory | Dev port | Purpose |
| --- | --- | --- | --- |
| POS | `/frontend` | `3000` | Floor, orders, kitchen, catalogue, inventory |
| Bono control | `/frontend-bono` | `3001` | Bonos, bar redeem, stock requests, reconciliation, billing, trial signup |

Both call `http://localhost:8000/api/v1` with a bearer token and send `X-Client: pos` or `X-Client: bono`. Entitlement-gated Bono routes live under `/api/v1/bono/*`. Totals, tax, and stock changes are calculated on the server.

## Features

### Sign-in and accounts

- Email and password login. The API returns a Sanctum personal access token. There is no cookie session.
- Inactive users cannot use the API.
- Each person lands on the screen their role actually uses: admin and manager on the dashboard, cashier on orders, waiter on tables, kitchen on the kitchen board.
- The sidebar only shows links the signed-in user is allowed to open. The API checks the same permissions again on every request. Admin is granted every permission.
- Account page to change the current password.
- Staff can clock in and clock out. A second open shift is rejected.

### Branches

- One restaurant can have more than one location.
- Admin can create a branch and optionally copy the current menu (categories, items, variants, and modifiers) into it.
- Admin and the owner switch the working branch from the top bar or Settings. Other staff stay on the branch they are assigned to.
- Tables, orders, kitchen tickets, inventory, purchases, expenses, customers, reservations, and reports always follow the working branch. Opening a second location does not mix its floor, orders, or stock with the first.
- Branch settings cover name, phone, address, tax ID, currency (default ETB), timezone (default Africa/Addis_Ababa), tax rate, and whether the branch is active. The restaurant name can be updated from the same screen.

### Floor and tables

- Table list with name, capacity, and status: available, occupied, reserved, or cleaning.
- Create, edit, and remove tables. A table with an open order cannot be removed.
- Assign a waiter to a table.
- Dine-in orders require a table. An occupied table cannot open a second order.
- Starting an order from a table marks that table occupied. Completing or cancelling the order frees it.

### Point of sale

- Full-screen POS for dine-in and takeaway.
- Browse the menu by category or search. Unavailable and inactive items are hidden.
- Add a line with quantity, an optional variant (the variant price replaces the base price), modifiers (extra injera, extra meat, and similar), and a note for the kitchen.
- Change quantity or remove a line. Removing a line, or reducing quantity after the ticket has been sent, is written to the audit log.
- Apply a discount. The discount cannot be negative and cannot exceed the subtotal. Discounts are audit-logged.
- Submit the order to the kitchen, mark it served, or cancel it. Cancellation requires a reason and is audit-logged.
- Attach a customer to the order.
- Order numbers are `ORD-YYYYMMDD-0001`, taken from a locked sequence row.

Order status moves only along this path:

`draft → submitted → preparing → ready → served → completed`

`cancelled` is allowed before the order is served. Completed and cancelled orders cannot move again. Invalid jumps are rejected.

### Kitchen

- Full-screen board of tickets that have been sent to the kitchen, grouped into New, Preparing, and Ready.
- The board refreshes every 5 seconds.
- Each item moves `new → preparing → ready`. Skipping a step is rejected.
- Item name, variant, modifiers, quantity, and notes are shown on the ticket.

### Orders, payments, and receipts

- Order list and order detail, filterable by status. Waiters see only orders they opened. Cashiers and managers see the branch’s orders.
- Split tenders: one order can be paid with more than one method. Methods are cash, card, mobile money, bank transfer, and other. Each payment can carry a reference and a note.
- A payment cannot exceed the remaining balance. Overpayment is rejected. The balance is computed on the server from the snapshotted line prices, modifiers, tax, and discount.
- A dine-in order completes when it is served and the balance is zero. A takeaway order can complete as soon as it is paid in full, without a serve step.
- Cancel or refund a payment. Refunds do not put ingredients back into stock.
- Printable receipt for an order.

Prices and tax are stored on the order at the time it is built. Changing a menu price later does not rewrite historical lines. The branch tax rate is applied on the server.

### Menu

- Categories with a description, sort order, and active flag. Categories can be created, renamed, and removed.
- Items with a base price, description, preparation time, availability, and active flag.
- Variants on an item (for example a size) with their own price.
- Modifiers with their own price, shared across items in the branch.
- Price changes are audit-logged.

The seeded menu is Betedesta’s house list: meat dishes, breakfast and firfir, fasting dishes, beer, soft drinks and water, and wine and spirits, with Amharic and English names.

### Inventory and recipes

- Stock items with SKU, unit (`kg`, `gram`, `liter`, `ml`, `piece`, `pack`), quantity on hand, and a minimum level.
- Quantities change only through transactions: purchase, usage, adjustment in, adjustment out, waste, and return. Manual edits do not overwrite the count.
- Low-stock items appear on the dashboard when quantity on hand is at or below the minimum.
- Recipes link a menu item to the ingredients and quantities it consumes.
- When an order completes, those ingredients are deducted once. A second completion cannot deduct again. A sale is still allowed if the count would go negative, so a busy service is not blocked by an imperfect stock count.

### Purchases and suppliers

- Supplier records: name, contact, phone, email, address, and notes.
- Purchase orders with line items, quantities, and unit cost. Purchase payment status is unpaid, partially paid, or paid.
- Receiving a purchase adds the quantities to inventory as a purchase transaction.

### Expenses

- Record branch expenses with a date, amount, payee, and note.
- Categories: rent, utilities, salary, gas, transport, maintenance, cleaning, supplies, and miscellaneous.
- Edit or delete an expense. Expense totals feed the dashboard’s estimated net and the expenses report.

### Customers and reservations

- Customer directory with name, phone, email, and notes. Search by name or phone.
- Customer profile shows order count, total spending on completed orders, and recent orders.
- Reservations with guest name, phone, date, time, guest count, optional table, and notes.
- Reservation status: pending, confirmed, seated, completed, cancelled, or no-show. The list can be filtered by date.

### Staff

- Create and update staff: name, email, phone, role, and branch. Deactivating a user blocks further API access. User create, update, and deactivate events are audit-logged.
- Roles: Administrator, Manager, Cashier, Waiter, Kitchen Staff.
- Today’s shifts for the branch, plus clock in and clock out for the signed-in user.

### Dashboard

Today’s snapshot for the working branch (the date range can be changed on the API):

- Gross sales, completed orders, average order value, discounts, tax, expenses, and estimated net (gross sales minus expenses).
- Floor counts: open orders, preparing, ready, occupied tables, and available tables.
- Low-stock items.
- Top 10 selling items by quantity.
- Sales by payment method and sales by waiter.

### Reports

Date-range reports for the working branch:

- Sales: total, order count, average order value, discounts, and tax. Can be filtered by waiter and payment method.
- Products: quantity sold and revenue by item name.
- Payments: totals and counts by method.
- Expenses: totals by category.
- Inventory: current quantities, low-stock items, and the latest stock movements.

### Audit log

Filterable history of sensitive actions, including who did it and the before/after values. Covered actions include discounts, order cancellation, submitted-item removal and quantity cuts, payment cancel and refund, menu price changes, inventory adjustments, staff changes, and branch changes.

## Who can do what

Admin can do everything below. The other roles are limited as follows.

| Area | Manager | Cashier | Waiter | Kitchen |
| --- | --- | --- | --- | --- |
| Dashboard | Yes | | | |
| POS and new orders | Yes | | Yes | |
| Tables | View, manage, status | View | View, status | |
| Orders | All, including cancel and discount | View all | Own orders only | |
| Payments and receipts | Take, cancel, refund, print | Take and print | | |
| Kitchen board | View | | | View and update status |
| Menu | View and edit | View | View | |
| Inventory and recipes | View and edit | | | |
| Suppliers and purchases | View and edit | | | |
| Expenses | View and edit | | | |
| Customers | View and edit | View and edit | View | |
| Reservations | View and edit | | View and edit | |
| Staff | View and manage | | | |
| Shifts | View and clock | Clock | Clock | Clock |
| Reports and audit log | Yes | | | |
| Branches | View | | | |
| Settings and new branches | | | | |

Managers can view branches. Creating branches, switching the working branch, and editing restaurant settings are admin capabilities.

## Business rules

1. Dine-in orders require a table. Occupied tables cannot open a second order.
2. Prices and tax are calculated on the backend from menu snapshots plus the branch tax rate.
3. Submitted item removals and quantity reductions are audit-logged.
4. Payments cannot exceed `balance_due`. Split tenders are allowed. Overpayment is rejected.
5. A dine-in order completes when it is served and the balance is zero. Takeaway may complete on full payment.
6. Invalid status jumps (for example `completed → preparing`) are rejected.
7. Menu price changes do not rewrite historical order line snapshots.
8. The owner operates one branch at a time. A second location does not share tables, orders, or stock with the first.
9. Inventory is deducted once, when the order completes. Refunds do not restock automatically.

## Primary workflow

Waiter signs in → selects an available table → adds items (variants, modifiers, notes) → sends to kitchen → kitchen prepares and marks ready → waiter serves → cashier takes payment (one or more methods) → receipt prints → table frees → ingredients deduct once → the sale appears on the dashboard and reports.

## Architecture

- **Auth:** Laravel Sanctum personal access tokens (Bearer).
- **Authorization:** `roles.slug` maps to permissions in `backend/config/permissions.php`. Admin bypasses via `Gate::before`. The Nuxt sidebar and route middleware only hide UI.
- **Money:** `decimal(15,2)` columns and `App\Support\Money` (bcmath). The frontend never supplies totals.
- **Order numbers:** `ORD-YYYYMMDD-0001` from a locked `sequences` row, with a unique index as a backstop.
- **Inventory deduction:** on order completion, via an `inventory_deducted_at` conditional update plus a unique inventory-transaction reference.
- **Kitchen:** polling every 5 seconds. Status transitions are validated (`new → preparing → ready`).

## Requirements

- PHP 8.3+ with `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `openssl`, `bcmath`
- Composer 2
- Node 20+
- SQLite for local development (MySQL 8 is supported by the same migrations)
- **Tesseract OCR** on the API host for transfer-receipt scanning (`tesseract-ocr` package, English trained data). Used by Bono control cashiers when uploading payment slips.

## Backend setup

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

The API listens on `http://localhost:8000/api/v1`.

Queue workers are optional for the MVP. Events are dispatched in-process (`QUEUE_CONNECTION=sync` is fine).

## Frontend setup (POS)

```bash
cd frontend
cp .env.example .env   # or create .env with NUXT_PUBLIC_API_BASE
npm install
npm run dev
```

POS listens on `http://localhost:3000`.

## Frontend setup (Bono control)

```bash
cd frontend-bono
cp .env.example .env   # or create .env with NUXT_PUBLIC_API_BASE
npm install
npm run dev
```

Bono control listens on `http://localhost:3001`. Restaurant trial signup is only in this app.

Allow both origins in the API:

```env
FRONTEND_URL=http://localhost:3000
FRONTEND_BONO_URL=http://localhost:3001
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001
```

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
