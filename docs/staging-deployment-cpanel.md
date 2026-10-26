# cPanel staging deployment checklist

Status: **prepared only — not executed.** No cPanel account, domain, DNS record,
database or cron job has been touched. Do not run these steps until the owner
approves the staging environment.

The application directory is assumed to be `/home/<CPANEL_USER>/chal-bazar-pro`
and the staging hostname `<staging-domain>`. Replace every `<...>` value with
the values confirmed on the actual host.

## 0. Non-negotiable hosting rules

- Laravel **must** be served from the `public/` directory. Never point the
  document root at the application root.
- `.env`, `app/`, `config/`, `database/`, `routes/`, `storage/`, `tests/`,
  `vendor/` and `.git/` must never be web-accessible.
- The staging site must use HTTPS, `APP_ENV=production` and `APP_DEBUG=false`.
- Do not import Firebase data, copy production customer data, send real
  WhatsApp messages or send real customer email during staging.
- Do not run `migrate:fresh`, `migrate:refresh`, `db:wipe` or a destructive
  restore against a database that contains real orders.

## 1. Verified locally (facts from this repository)

| Item | Verified value | Where verified |
| --- | --- | --- |
| PHP requirement | `^8.3`; tested on PHP 8.4.27 | `composer.json`, `php -v` |
| Framework | Laravel 13.38.0 | `composer.lock` |
| Livewire | 4.4.8 | `composer.lock` |
| PDF engine | mPDF 8.3.1 | `composer.lock` |
| Databases tested | SQLite (in-memory test DB) and MySQL 8.4 test DB | `php artisan test` |
| Routes | 41 routes, including `orders.invoice.pdf` and `admin.orders.return` | `php artisan route:list` |
| Scheduler | `orders:expire-reservations` every 10 minutes, `withoutOverlapping(10)` | `php artisan schedule:list`, `routes/console.php` |
| Queue | No queued jobs are dispatched by the current code | repository search |
| Session / cache / queue | `database` drivers by default | `.env.example` |
| Document root | `public/` only | `public/index.php`, Laravel layout |
| PDF files | Generated on demand and streamed; nothing written to disk | `InvoiceController`, `MpdfInvoicePdfRenderer` |
| Private disk | `storage/app/private` (outside the web root) | `config/filesystems.php` |
| WhatsApp | Click-to-chat link only; no server-side sending and no API token | `config/whatsapp.php`, `OrderConfirmationLink` |
| Cloudinary | No SDK, config or env integration; only a nullable column | repository search |
| Build | `npm run build` succeeds | Vite 8.3.5 build |
| Composer | `composer validate` and `check-platform-reqs` pass | Composer 2 checks |

## 2. Hosting requirements

### 2.1 PHP

| Requirement | Value | Status |
| --- | --- | --- |
| PHP version | 8.4 recommended; 8.3 minimum | Verified locally on 8.4.27; host support **unknown** |
| PHP 8.5 | Not tested | **Unknown** |
| Memory limit | 256 MB recommended (mPDF peak was ~18 MB for a one-page invoice) | Host limit **unknown** |
| Max execution time | 60 seconds or more for PDF generation | Host limit **unknown** |
| OPcache | Recommended | Host support **unknown** |

Required extensions (from the installed packages and a production run):

`ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `iconv`, `json`,
`mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `phar` (Composer),
`session`, `tokenizer`, `xml`, `xmlwriter`, `zlib`.

Notes:

- `gd` is required by mPDF. `bcmath` is only needed for some mPDF barcode
  types; it is not required by the current invoice.
- `gmp`, `intl`, `imagick` and Redis are not required by this application.
- `pdo_mysql` is mandatory for MySQL/MariaDB. SQLite is for tests only.
- Composer itself needs `phar`, `openssl` and `zip` (or an external unzip
  helper). If `zip` is unavailable, use `composer install --prefer-dist` with
  the host's unzip binary.

Enable these in cPanel **MultiPHP INI Editor** or the PHP Selector. Verify with:

```bash
php -m
php -v
php artisan about
```

`php artisan about` does not print secrets, but check the output before sharing
it publicly.

### 2.2 MySQL / MariaDB

| Requirement | Value | Status |
| --- | --- | --- |
| MySQL | 8.0+; tested on MySQL 8.4 | MySQL 8.4 verified locally; host version **unknown** |
| MariaDB | 10.6+ is likely compatible | **Not tested** |
| Storage engine | InnoDB | Required |
| Charset / collation | `utf8mb4` / `utf8mb4_unicode_ci` (Laravel default) | Verify on host |
| SQL mode | Default MySQL 8 modes are fine | Unknown host override |

Create a dedicated database and user in cPanel. Recommended privileges:

- Runtime user: `SELECT`, `INSERT`, `UPDATE`, `DELETE` on the application
  database.
- Migration/backup user (or temporary elevated privileges): `CREATE`, `ALTER`,
  `INDEX`, `DROP`, `REFERENCES`, `CREATE TEMPORARY TABLES`, `LOCK TABLES`,
  `SHOW VIEW`, `TRIGGER`.
- cPanel often gives one user all privileges; if so, record that and use a
  separate backup routine. Do not grant global/server-wide privileges.

Database name/user/password go only in `.env`. Never commit them.

### 2.3 Composer and shell access

| Option | Requirement | Status |
| --- | --- | --- |
| Preferred | SSH or cPanel **Terminal** with Composer 2.x and the selected PHP CLI | Host availability **unknown** |
| Alternative | Build `vendor/` locally on the same PHP major/minor, upload it, and run `composer check-platform-reqs` on the host | Workable but must match PHP/extensions |
| Not acceptable | Committing `vendor/` to Git | Prohibited |

Commands (from the application root, using the host PHP CLI path):

```bash
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs
```

If the host has no Composer, upload a `vendor/` directory produced with
`composer install --no-dev --optimize-autoloader` on a matching PHP platform.
Never upload the local `.env`.

### 2.4 Domain, subdomain and document root

1. Create the staging subdomain in cPanel (for example
   `staging.example.com`).
2. Set its document root to `/home/<CPANEL_USER>/chal-bazar-pro/public`.
3. If cPanel refuses a document root outside `public_html`, deploy the
   application to `/home/<CPANEL_USER>/chal-bazar-pro` and point the subdomain
   there; do **not** move `index.php`, `.env` or `storage/` into
   `public_html`.
4. Confirm `https://<staging-domain>/.env` returns 404/403, and that directory
   listing is disabled.
5. The application URL must be the HTTPS staging URL:

```dotenv
APP_URL=https://<staging-domain>
```

`APP_URL` affects generated links, the Vite manifest URLs and the public disk
URL. It does not by itself force HTTPS.

### 2.5 HTTPS

- Enable AutoSSL / Let's Encrypt for the subdomain.
- Force HTTPS at the web-server level (cPanel **Force HTTPS Redirect** or an
  `.htaccess` rule) and verify with `curl -I http://<staging-domain>`.
- In `.env`:

```dotenv
SESSION_SECURE_COOKIE=true
```

- Do not enable HSTS until the staging hostname is final and HTTPS is proven.
- If TLS terminates at a proxy, configure trusted proxies at the web-server
  level; the application does not currently define `TRUSTED_PROXIES`.

### 2.6 Writable directories

The PHP user (often the cPanel account user under PHP-FPM) must be able to
write:

```text
storage/
storage/app/private/
storage/app/public/
storage/app/mpdf/
storage/framework/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/logs/
bootstrap/cache/
```

Use `755` for directories and `644` for files where possible; use `775` for
the writable directories above if the web user is in the account group. Do not
use `777`.

`storage/app/mpdf` is only mPDF's font-metric cache. It can be deleted at any
time and must not be backed up as business data.

### 2.7 Private invoice storage and downloads

- No invoice PDF is stored. `GET /orders/{order}/invoice/pdf` renders the PDF
  from the issued invoice and historical `order_items` snapshots and streams
  it with `Cache-Control: private, no-store`.
- Access is authorized against the **order**: the owning customer, or an
  administrator with the `invoices` permission. A raw invoice/order id from
  the browser is not proof of access.
- `storage/app/private` is outside the document root. The framework's
  `storage/{path}` route (from `'serve' => true`) only serves files with a
  valid signed URL; the invoice feature does not use it.
- If invoice files are ever persisted in a later milestone, keep them under
  `storage/app/private` and serve them through an authorized controller or a
  short-lived signed URL. Never put them under `public/` or `public/storage`.

### 2.8 Cron for reservation expiry

The application schedules:

```text
orders:expire-reservations   every 10 minutes   withoutOverlapping(10)
```

cPanel cron runs the Laravel scheduler every minute. Add **one** entry in
cPanel → Cron Jobs:

```cron
* * * * * /usr/local/bin/php /home/<CPANEL_USER>/chal-bazar-pro/artisan schedule:run >> /dev/null 2>&1
```

Replace `/usr/local/bin/php` with the verified PHP 8.3/8.4 CLI path
(`which php`, `php -v`, or the cPanel PHP Selector path such as
`/opt/cpanel/ea-php84/root/usr/bin/php`). The exact path is **unknown** until
the host is inspected.

Why the scheduler needs a working database: the task uses
`withoutOverlapping()`, which takes a cache lock. The default cache store is
`database`, so the `cache` and `cache_locks` tables must exist and be
writable. This was confirmed when `schedule:list` failed with an unreachable
local database cache.

Do not add the cron entry until staging is approved. To test manually on
staging, first take a backup, then run with a small limit:

```bash
php artisan orders:expire-reservations --limit=1
```

There is no dry-run option. The command only cancels **pending** orders whose
reservation has expired; it never touches accepted, dispatched, delivered or
cancelled orders.

### 2.9 SMTP and email

Current `.env.example` uses `MAIL_MAILER=log`, so password-reset mail is not
delivered until SMTP is configured. This is a **staging blocker** for testing
password reset and for production launch.

Required values (from the cPanel email account or an external SMTP provider):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587            # 465 for implicit TLS
MAIL_SCHEME=             # empty = auto: STARTTLS on 587, implicit TLS on 465
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-password>
MAIL_FROM_ADDRESS=<verified-sender@example.com>
MAIL_FROM_NAME="${APP_NAME}"
```

- Configure SPF, DKIM and DMARC in cPanel → Email Deliverability.
- Test with a staging mailbox you control. Do not send to real customers.
- `APP_URL` must be correct or reset links point at the wrong host.

### 2.10 WhatsApp and Cloudinary

WhatsApp:

```dotenv
WHATSAPP_ENABLED=true
WHATSAPP_BUSINESS_NUMBER=8801XXXXXXXXX
WHATSAPP_BUSINESS_NAME="Chal Bazar Pro"
```

- Digits only, international format, no `+` and no separators.
- No WhatsApp Cloud API token or webhook is used. The server only builds a
  `wa.me` link; the customer reviews and presses send in WhatsApp.
- Invalid/empty number hides the button. Verify the link is visible only to the
  order owner and not to staff, other customers or cancelled orders.

Cloudinary:

- Not integrated. There is no SDK, config key or env variable.
  `product_media.cloudinary_public_id` is only a nullable column.
- **No Cloudinary credentials are needed for staging.**
- Product images currently come from the stored `product_media.url` values.
  Verify the staging catalogue has valid image URLs before smoke-testing the
  storefront; do not configure Cloudinary as part of this deployment.

### 2.11 Logging, errors and monitoring

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

- `storage/logs/` must be writable. Keep at least 14 days; use cPanel disk
  quota monitoring.
- Never expose stack traces to visitors. The invoice PDF failure path already
  returns a generic `503` page and logs only order id, invoice id and exception
  class.
- Monitor 5xx responses, disk usage, failed logins and cron execution. cPanel
  does not provide application-level error tracking; add external uptime
  monitoring if required.
- Do not log `.env`, session tokens, passwords or full request bodies.

### 2.12 Production configuration and cache

Generate a **new** application key on staging (do not copy production secrets):

```bash
php artisan key:generate --force
```

Production `.env` minimum (values omitted):

```dotenv
APP_NAME="Chal Bazar Pro"
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated>
APP_URL=https://<staging-domain>

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<db>
DB_USERNAME=<user>
DB_PASSWORD=<password>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

ORDER_RESERVATION_TTL_HOURS=24
ORDER_RESERVATION_EXPIRY_BATCH_SIZE=100

INVOICE_PDF_ENABLED=true
INVOICE_BUSINESS_NAME="Chal Bazar Pro"
INVOICE_BUSINESS_NAME_BN="চাল বাজার প্রো"
INVOICE_BUSINESS_PHONE=<shop-phone>
INVOICE_BUSINESS_ADDRESS=<shop-address>

WHATSAPP_ENABLED=true
WHATSAPP_BUSINESS_NUMBER=<880...>
WHATSAPP_BUSINESS_NAME="Chal Bazar Pro"

MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_SCHEME=
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-password>
MAIL_FROM_ADDRESS=<sender>
MAIL_FROM_NAME="${APP_NAME}"
```

Optimize only after `.env` is final and migrations have run:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`config:cache`, `route:cache` and `view:cache` were verified locally. If a
deployment changes `.env`, run `php artisan config:clear` before editing, then
re-cache. Clear all caches during rollback:

```bash
php artisan optimize:clear
```

No queue worker is required by the current code (`QUEUE_CONNECTION=database`
is retained for future use). If a queued job is added later, add a supervised
`php artisan queue:work` process; cPanel shared hosting may not support a
long-running worker.

### 2.13 First owner account

Create the owner from the CLI, not from a web route:

```bash
php artisan app:provision-owner owner@example.com --name="Store Owner" \
  --allow-production
```

Use a unique strong password. Do not reuse the local password. The command
refuses to run in production without `--allow-production` and never prints the
password.

### 2.14 Known application limitations relevant to the host

- The application timezone is hardcoded to `UTC` in `config/app.php`. Invoice
  and order timestamps display in UTC. Decide before launch whether the shop
  needs `Asia/Dhaka`; changing it affects date display and is a code change,
  not a cPanel setting.
- `APP_LOCALE` defaults to `en`; the storefront text is Bangla, but framework
  validation messages may be English unless translated.
- Email verification is not enabled; password reset requires SMTP.
- No payment gateway is integrated (COD only).
- The framework's local-disk serving route (`storage/{path}`) is enabled by
  `'serve' => true` in `config/filesystems.php`. It only serves signed URLs,
  but if it is not needed, disabling `serve` removes that route in a later
  hardening change.

## 3. Verified vs unknown on the host

| Item | Verified locally | Unknown until host inspection |
| --- | --- | --- |
| PHP 8.3/8.4 and extensions | Yes (8.4.27) | Host version, extensions, INI limits |
| MySQL 8.4 | Yes (test DB) | Host MySQL/MariaDB version, SQL mode |
| SSH/Terminal + Composer | Local Composer 2 | Host access and path |
| Document-root override | Laravel public layout | Whether cPanel allows `public/` docroot |
| HTTPS / AutoSSL | Not tested | Certificate and redirect support |
| Cron PHP path | Scheduler works locally | Real CLI path and cron permissions |
| SMTP | Not configured | Credentials, deliverability, SPF/DKIM |
| Backup/restore | Procedure prepared | cPanel backup retention and off-site storage |
| Disk/memory limits | n/a | Host quotas and PHP memory limit |
| Outbound network | Not needed except SMTP | Firewall policy |

## 4. Staging deployment sequence (not executed)

1. **Freeze and tag.** Confirm local `main` is clean and matches `origin/main`.
   Create an annotated Git tag for the release and push it. Record the commit
   hash in the staging log.
2. **Back up.** Dump the current staging database and archive the current
   staging application directory and `.env` (see
   `docs/backup-and-rollback.md`). If staging is empty, record that fact.
3. **Create the hosting resources.** Subdomain, document root, database,
   database user, PHP version/extensions, SSL.
4. **Upload the release.** Prefer `git archive`/rsync of the tagged commit, or a
   Git checkout on the host. Exclude `.env`, `vendor/`, `node_modules/`,
   `public/build/`, `.git/`, tests and local databases.
5. **Install dependencies.** `composer install --no-dev --optimize-autoloader`
   (or upload a matching `vendor/`), then `composer check-platform-reqs`.
6. **Configure `.env`.** Copy the production template, generate `APP_KEY`, set
   the staging URL, DB, SMTP, invoice and WhatsApp values. `chmod 600 .env`.
7. **Build assets.** Run `npm ci && npm run build` on the host if Node is
   available; otherwise upload the locally built `public/build/` produced from
   the same commit. Verify `public/build/manifest.json` exists.
8. **Migrate.** `php artisan migrate --force`. These migrations are additive
   for a new database; for an existing staging database, review
   `php artisan migrate:status` first. Never use `fresh`/`refresh`.
9. **Link public storage (only if public media is used).**
   `php artisan storage:link`.
10. **Provision the owner.** `app:provision-owner ... --allow-production`.
11. **Optimize.** `config:cache`, `route:cache`, `view:cache`.
12. **Cron.** Add the single `schedule:run` entry, wait for one run and confirm
    no errors in `storage/logs/laravel.log`.
13. **Smoke test.** Follow section 5.
14. **Record.** Commit hash, tag, DB backup name, `.env` backup location,
    cron line, and smoke-test results in the staging log.

## 5. Staging smoke tests

- [ ] `https://<staging-domain>/` loads over HTTPS; HTTP redirects to HTTPS.
- [ ] `/.env`, `/.git/config`, `/composer.json`, `/storage/logs/laravel.log`
      return 404/403.
- [ ] Debug is off: a deliberately invalid URL returns a generic 404, not a
      stack trace.
- [ ] Register a test customer; login/logout; suspended/blocked login denied.
- [ ] Product list and product detail show correct Bangla text and images.
- [ ] Add to cart and complete a COD checkout against the staging DB.
- [ ] Customer order page shows the order; another customer cannot open it.
- [ ] Printable HTML invoice opens for the owner; PDF downloads with embedded
      Hind Siliguri and correct Bangla totals.
- [ ] PDF endpoint is denied to another customer and to staff without the
      `invoices` permission.
- [ ] WhatsApp button appears only for the order owner, opens `wa.me` with a
      prepared message, and contains no address, email, token or secret.
- [ ] Admin login; order accept → dispatch → deliver; stock changes once.
- [ ] Shop cancel of a dispatched order does **not** restock; a verified return
      with a mandatory reason restocks once.
- [ ] Duplicate return operation key does not double-restock.
- [ ] Password reset sends through SMTP to a controlled staging mailbox.
- [ ] `php artisan schedule:list` shows `orders:expire-reservations`.
- [ ] Cron has run at least once (check log/cache lock; no error output).
- [ ] `php artisan migrate:status` shows all migrations `Ran`.
- [ ] `php artisan about` shows `production`, debug off, correct URL and DB.

## 6. Do not do during staging

- Do not change DNS for the production domain.
- Do not point staging at the production database.
- Do not import Firebase data or copy real customer records.
- Do not send real WhatsApp messages, real customer emails or external API
  requests.
- Do not add a live cron job before approval.
- Do not run destructive migrations or restore a production dump into staging.
- Do not expose `.env`, logs, SQLite files or invoice PDFs.
