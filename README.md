# Lending Management System

Web-based lending system with a savings ledger and multi-Area audit log, built for Agreement **KBK-2026-LMS-001** (KodebyKarl).

Laravel 12 · Filament 5 · MySQL (SQLite locally) · PHP 8.2+

## What it does

| Module | Features |
|---|---|
| **Lending** | Borrowers (auto `BRW-0001`), loans with automatic repayment schedule, payment posting to the oldest unpaid installment, automatic Active / Overdue / Paid status |
| **Loan plans** | The owner sets interest and term per plan. Staff pick a plan and cannot change its interest or term (enforced on the server) |
| **Savings** | One account per borrower, deposits and withdrawals (no overdraw, including back-dated), running-balance ledger, printable statement |
| **Areas** | Three Areas. Staff see and record only their own Area; the administrator sees everything, per Area or consolidated |
| **Audit log** | Logins, logouts, failed logins, and every create / edit / delete with before-and-after values. Read-only; entries cannot be edited or deleted |
| **Receipts** | Staff type the number from the Area's pre-printed booklet (required, unique per Area across loan payments and savings deposits). Printable Acknowledgment Receipt (borrower and office copies) with balance before and after. Entries go in date order and only the latest can be deleted, so a printed receipt always matches the ledger. Receipt register flags missing numbers |
| **Dashboard** | Collected today, outstanding, past due, savings, daily-collections chart, overdue loans, recent payments |
| **Reports** | Daily collection sheet, payment history, receipt register, outstanding loans, overdue loans, savings summary, borrower loan history, loan statement. All print from the browser |

Out of scope (Agreement §2.5): penalties, interest on savings, SMS, online payments, Excel/PDF export, importing old records.

## Run it locally

Uses XAMPP's PHP on Windows (`C:\xampp\php`, with `intl`, `zip`, `gd`, `sodium`, `sqlite3` enabled).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed                  # Areas, accounts (password: "password"), starter plans
php artisan db:seed --class=DemoSeeder      # optional: 24 sample borrowers with payments
php artisan serve                           # http://127.0.0.1:8000
```

Local logins: `admin@lender.test`, `staff1@lender.test` … `staff3@lender.test`, password `password`.

## Tests

```bash
php artisan test
```

65 tests cover the loan math, payments, receipts, savings, Area isolation, loan plans, audit log, dashboard figures, and every report. They run on SQLite by default and also pass on MySQL/MariaDB:

```bash
DB_CONNECTION=mysql DB_DATABASE=lending_test DB_USERNAME=root php artisan test
```

## Where things are

| Path | What |
|---|---|
| `app/Services/LoanCalculator.php` | Repayment schedule math (flat and diminishing; in centavos so totals are exact) |
| `app/Services/LoanService.php` | Create loan, record or delete payment, reallocate, overdue status |
| `app/Services/SavingsService.php` | Deposits, withdrawals, running balances |
| `app/Services/ReceiptBook.php` | Receipt-number rules (required, unique per Area) |
| `app/Services/ReportService.php` | Every dashboard and report figure |
| `app/Models/Concerns/BelongsToArea.php` | Area isolation and audit tagging |
| `app/Policies/` | Admin-only screens (Areas, Users, Loan plans, Audit log) |
| `app/Filament/` | Screens: resources, dashboard widgets, Reports page |
| `resources/views/print/` | Printable reports and statements |
| `config/lending.php` | Lending rules from Annex A (grace days, Sunday collection, defaults, setup accounts) |

## Lending rules to confirm with the Client (Annex A)

All of these are settings; no code changes are needed.

- **Days before Overdue:** `LENDING_OVERDUE_GRACE_DAYS` (default 0, so one missed day marks the loan Overdue)
- **Sunday collection:** `LENDING_COLLECT_ON_SUNDAYS` (default true)
- **Interest and terms:** set by the owner in **Administration → Loan plans**
- **Area names and accounts:** `SETUP_*` in `.env` before the first `db:seed`, or edit them later in **Administration**
- **Business name on printouts:** `LENDING_BUSINESS_NAME`
- **Open question:** whether interest or fees are deducted at release. Not supported yet.

Deployment: see [DEPLOYMENT.md](DEPLOYMENT.md).
