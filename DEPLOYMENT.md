# Deployment and Turnover

How to put the system on the Client's hosting (Agreement §3 and §6.2) and hand it over (Annex B).

> Deploy only **after the 40% balance is received** (§6.2). Until then, demo from your own environment.

## 1. Hosting requirements

Buy these under the **Client's name** (§3.2). Expect about ₱2,500–₱4,500 a year.

- PHP **8.2 or newer** with extensions: `intl`, `zip`, `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `bcmath`, `curl`
- MySQL 8 or MariaDB 10.4+
- **SSH access** and **Composer** (most business-tier shared hosting has both)
- **Cron jobs** (for the daily Overdue check)
- Free SSL (Let's Encrypt) and daily backups from the hosting provider

## 2. First deployment

```bash
# 1. Upload the code (git clone, or upload a zip without vendor/ and node_modules/)
cd ~/lending-system

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Environment
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<client-domain>

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<database>
DB_USERNAME=<user>
DB_PASSWORD=<password>

SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning

LENDING_BUSINESS_NAME="<Client business name>"
LENDING_OVERDUE_GRACE_DAYS=<from Annex A>
LENDING_COLLECT_ON_SUNDAYS=<true|false>

SETUP_ADMIN_NAME="<owner name>"
SETUP_ADMIN_EMAIL=<owner email>
SETUP_AREA_1_NAME="<Area 1>"
SETUP_AREA_1_STAFF_NAME="<staff name>"
SETUP_AREA_1_STAFF_EMAIL=<staff email>
# …same for Areas 2 and 3
```

```bash
# 4. Database, accounts, and starter loan plans
php artisan migrate --force
php artisan db:seed --force
```

`db:seed` prints a table of **random passwords**, shown only once. Copy them into the turnover sheet. Each user can change their password later under their profile menu.

> **Never run `DemoSeeder` on the live server.** It fills the database with sample borrowers.

```bash
# 5. Cache for speed (run again after every update)
php artisan optimize
php artisan filament:optimize
```

### Point the domain at `public/`

The web root must be the `public/` folder, not the project folder.

- **If the panel lets you set the document root:** set it to `~/lending-system/public`.
- **If it is fixed to `public_html`:** keep the project outside `public_html` and replace `public_html` with a link:
  ```bash
  rm -rf ~/public_html && ln -s ~/lending-system/public ~/public_html
  ```

Then turn on SSL in the hosting panel and confirm `https://` loads the sign-in page.

### Scheduled tasks (required)

Add this **cron job** to run every minute. It marks loans Overdue every day at 12:05 AM:

```
* * * * * cd ~/lending-system && php artisan schedule:run >> /dev/null 2>&1
```

Check that it is registered: `php artisan schedule:list` should show `loans:refresh-status`.

### Permissions

`storage/` and `bootstrap/cache/` must be writable by PHP:

```bash
chmod -R 775 storage bootstrap/cache
```

## 3. Backups (§9.2)

1. Turn on the hosting provider's **daily automatic backups**, including the database.
2. Extra copy (recommended): a weekly database dump that the owner downloads monthly:
   ```
   0 2 * * 0 mysqldump -u<user> -p'<password>' <database> | gzip > ~/backups/lending-$(date +\%F).sql.gz
   ```
3. Test a restore once before turnover.

After the 1-month support period, keeping backups active is the Client's responsibility (§9.2).

## 4. Updating later

```bash
cd ~/lending-system
php artisan down
git pull                     # or upload the changed files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan filament:optimize
php artisan up
```

## 5. Before turnover: smoke test on the live site

Sign in as **admin**:

- [ ] Dashboard loads; the Area filter switches between Areas and "All areas"
- [ ] Loan plans match the Client's rates and terms (Annex A)
- [ ] Create a borrower, a loan from a plan, and record a payment; the balance and schedule update
- [ ] Open a savings account, deposit, then try to withdraw more than the balance (must be refused)
- [ ] Every report opens and prints (Reports menu, plus a loan statement and a savings statement)
- [ ] Audit log shows the sign-in and the changes above

Sign in as **staff of Area 1**:

- [ ] Sees only Area 1 data; the Administration menu is not visible
- [ ] Interest and term are locked when creating a loan

Then **delete the test records**, or start clean with `php artisan migrate:fresh --seed --force` **before** any real data is entered.

## 6. Turnover checklist (Annex B)

| Annex B item | How to confirm |
|---|---|
| System deployed and operational at: `https://…` | Sign-in page loads over https; smoke test above passed |
| Borrower, Loan, Repayment Schedule, Payment, Dashboard, and Report modules working | Smoke test, admin section |
| Savings Module and Savings Ledger working | Deposit, refused overdraw, printed statement |
| Three Areas, Area user accounts, and Audit Log working | Staff section of the smoke test; audit entries visible |
| Administrator credentials, source code, and hosting access turned over | Owner's login (from the `db:seed` table), staff logins, source code (zip or repository), hosting and database credentials |
| Orientation conducted and user guide received | One session for the admin and Area staff (§2.4) |

Have the Client sign **Annex B** on the same day. Acceptance starts the 1-month bug-fix support period (§12.1).
