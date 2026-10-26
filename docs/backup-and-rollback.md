# Backup and rollback plan

Scope: cPanel staging and, later, production. **Nothing in this document has
been executed against the live host.** No production data was exported and no
restore was run during the audit.

## 1. What must be backed up

| Item | Why | Location / command |
| --- | --- | --- |
| MySQL database | Orders, order items, status history, stock ledger, stock levels, reservations, returns, invoices, invoice sequence, users, sessions/cache (database drivers) | `mysqldump` (see below) |
| Application `.env` | APP_KEY, DB/SMTP/WhatsApp/invoice configuration | Encrypted archive, never in Git or the web root |
| Application release | Exact code that is running | Git tag + commit hash; optional tarball |
| `storage/app/private/` | Private files (currently no invoice PDFs are stored, but the directory is reserved) | File archive |
| `storage/app/public/` | Uploaded public media, if any | File archive or object storage |
| `public/build/` | Compiled Vite assets if built on the host | Included in the release tarball, or rebuild from the same commit |
| `resources/fonts/hind-siliguri/` | PDF font and OFL attribution | Already in Git; also preserved in every release archive |
| `storage/logs/` | Incident evidence | Optional; rotate, do not keep forever |

Not business data:

- `storage/app/mpdf/` is a disposable font-metric cache.
- `storage/framework/cache`, `sessions` and `views` are runtime caches; they can
  be cleared.
- `vendor/` can be reinstalled from `composer.lock`.
- `node_modules/` can be reinstalled from `package-lock.json`.

## 2. Release identifiers

Before deploying:

```bash
git status --short
git log -1 --oneline
git tag -a deploy-YYYY-MM-DD-staging -m "Staging release YYYY-MM-DD"
git push origin main --tags
```

Record in the deployment log:

- branch and commit hash,
- tag name,
- database backup filename and checksum,
- `.env` backup location,
- cron line,
- who approved the deployment.

Tags are lightweight recovery anchors. Never rewrite published history and
never force-push.

## 3. Database backup

Run from a shell on the host (or via cPanel's backup tool if SSH is not
available). Do not put dumps under `public/`.

```bash
mkdir -p /home/<CPANEL_USER>/backups
mysqldump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  -h 127.0.0.1 -u <DB_USER> -p \
  <DB_NAME> \
  | gzip > /home/<CPANEL_USER>/backups/<DB_NAME>-$(date +%Y%m%d-%H%M%S).sql.gz
```

- `--single-transaction` gives a consistent InnoDB snapshot without locking the
  shop for the whole dump.
- `mysqldump` writes `SET TIME_ZONE='+00:00'` by default (`--tz-utc` is on),
  so TIMESTAMP values are dumped as UTC instants and restore correctly
  regardless of the session timezone. Do not strip that statement. The
  application reads them back as Asia/Dhaka through `DB_TIMEZONE=+06:00`.
- The database user needs `SELECT`, `LOCK TABLES`, `SHOW VIEW`, `TRIGGER` and
  (for routines) `SHOW ROUTINE`/`EXECUTE` as applicable.
- Verify the dump immediately:

```bash
gzip -t <dump>.sql.gz
sha256sum <dump>.sql.gz > <dump>.sql.gz.sha256
```

- Keep at least one copy off the cPanel account (local encrypted disk or
  approved object storage). A backup on the same disk is not a backup.
- Retain a daily set and a pre-deployment set. Define the retention period with
  the owner; cPanel backup retention is **unknown** until the host is checked.
- Encrypt backups if they leave the host. They contain customer names,
  addresses, emails and order history.

## 4. File and environment backup

```bash
tar -czf /home/<CPANEL_USER>/backups/app-$(date +%Y%m%d-%H%M%S).tar.gz \
  -C /home/<CPANEL_USER> \
  --exclude='chal-bazar-pro/vendor' \
  --exclude='chal-bazar-pro/node_modules' \
  --exclude='chal-bazar-pro/storage/logs' \
  chal-bazar-pro

cp /home/<CPANEL_USER>/chal-bazar-pro/.env \
   /home/<CPANEL_USER>/backups/.env-$(date +%Y%m%d-%H%M%S)
chmod 600 /home/<CPANEL_USER>/backups/.env-*
```

If the host has no SSH, use cPanel → File Manager to archive the application
directory, and cPanel → Backup for the database. Keep `.env` out of any archive
that is downloaded to an untrusted machine.

## 5. Testing a database restore (do not touch production)

Never test a restore by importing into the live database. Use a separate,
disposable database.

1. Create an empty database and user in cPanel, for example
   `chal_bazar_restore_test`.
2. Import the dump:

```bash
gunzip -c <dump>.sql.gz | mysql -h 127.0.0.1 -u <TEST_USER> -p <TEST_DB>
```

3. Run migrations in **status-only** mode against the test database; do not run
   `migrate` unless you are deliberately testing it. If configuration is
   cached, clear it first so the override is honoured:

```bash
php artisan config:clear
DB_DATABASE=<TEST_DB> DB_USERNAME=<TEST_USER> DB_PASSWORD=<...> \
  php artisan migrate:status
```

4. Compare business counts with the source (adjust the list to the release):

```sql
SELECT COUNT(*) FROM orders;
SELECT COUNT(*) FROM order_items;
SELECT COUNT(*) FROM order_status_events;
SELECT COUNT(*) FROM stock_movements;
SELECT COUNT(*) FROM stock_levels;
SELECT COUNT(*) FROM stock_reservations;
SELECT COUNT(*) FROM order_return_batches;
SELECT COUNT(*) FROM order_returns;
SELECT COUNT(*) FROM invoices;
SELECT period, last_number FROM invoice_sequences ORDER BY period;
```

5. Reconcile stock:

```sql
-- On-hand must equal the physical movement ledger for every variant.
SELECT l.product_variant_id,
       l.on_hand_kg,
       COALESCE(SUM(CASE WHEN m.direction = 'in' THEN m.quantity_kg
                         ELSE -m.quantity_kg END), 0) AS ledger_kg
FROM stock_levels l
LEFT JOIN stock_movements m
  ON m.product_variant_id = l.product_variant_id
 AND m.stock_bucket = 'physical'
GROUP BY l.product_variant_id, l.on_hand_kg
HAVING ABS(l.on_hand_kg - ledger_kg) > 0.00005;
```

The query must return **no rows**. Also verify a sample order's status history
and total against the invoice.

Also verify the timezone round-trip against the restore copy:

```bash
php artisan tinker --execute="echo DB::selectOne('SELECT @@session.time_zone AS tz')->tz;"
```

Expected: `+06:00`. Compare the restored `orders.created_at` and
`invoices.issued_at` for one sample row with the source: the Dhaka wall-clock
value must be identical (the underlying UTC instant is unchanged). If the
values differ by six hours, the dump was taken without `--tz-utc` or the
session timezone was not applied — stop and fix the backup procedure.

6. Open the restored site only if it is isolated (separate subdomain, separate
   `.env`, separate DB). Never point the production `.env` at the restore test
   database.
7. Drop the disposable test database after verification, and record the
   restore-test result in the deployment log.

## 6. Rollback scenarios

### 6.1 Code-only rollback (no incompatible migration)

This is the normal staging rollback. It keeps all orders and stock movements.

```bash
# On the host, in the application directory
git fetch --tags origin
git checkout <previous-tag-or-commit>
composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- Keep the current `.env` and database. Do not restore the old database just
  because the code changed.
- Rebuild or re-upload `public/build/` from the rolled-back commit.
- If the rolled-back code cannot read a newly added table/column, stop and use
  section 6.3; do not drop the new data.

### 6.2 Disable the failing feature without a full rollback

- PDF problem: set `INVOICE_PDF_ENABLED=false`, `php artisan config:cache`. The
  printable HTML invoice remains. This does not touch invoice numbers or stock.
- WhatsApp problem: set `WHATSAPP_ENABLED=false` or clear
  `WHATSAPP_BUSINESS_NUMBER`; the button disappears. No message was ever sent
  by the server.
- Expiry/cron problem: remove or comment the cPanel cron entry. Pending orders
  stay pending; no stock is released until the scheduler runs again.
- SMTP problem: set `MAIL_MAILER=log` to stop delivery attempts; password reset
  will not send until SMTP is fixed.

### 6.3 Rollback when a migration must be undone

The migrations in this repository are additive in `up()` (the `down()` methods
drop tables/columns). On a live shop, **do not** use `migrate:rollback`:

- `down()` drops columns and tables that may already hold real orders,
  movements and returns.
- Rolling back the schema does not roll back the business events that occurred
  after the deployment.

Safe procedure:

1. Put the site in maintenance mode (`php artisan down --retry=60`).
2. Take a fresh database dump and file archive.
3. Deploy the last known-good code (tag).
4. If the old code can read the current schema (additive column/table), leave
   the schema in place. This is the preferred outcome.
5. If it cannot, write and review a forward-only compensating migration that
   preserves the new tables/columns, or restore the pre-deployment dump to a
   **new** database, verify it, and only then switch `.env` after reconciling
   orders/stock created since the backup.
6. Never restore an old dump over a database that has newer orders without an
   explicit reconciliation plan approved by the owner.
7. Run `php artisan migrate:status`, clear caches, bring the site up, and repeat
   the smoke tests.

### 6.4 Full staging reset (only if staging is disposable)

If staging contains no real customer data, a clean rebuild is acceptable:
delete the staging database, create a new one, run `php artisan migrate
--force`, provision the owner, and reseed the catalogue. Still keep the old
dump until the new staging passes smoke tests.

## 7. Reverting a failed staging release without losing orders

The rule is: **roll back code, keep data.**

1. Remove the cron entry (or leave it; it is idempotent) and put the site in
   maintenance mode if the failure affects checkout.
2. Confirm the last good tag and commit.
3. Take a fresh dump even though the release failed; it may contain orders
   placed during the failed window.
4. Check out the last good tag, reinstall dependencies, rebuild assets.
5. Keep the database. Additive migrations mean the old code usually ignores new
   columns/tables.
6. Clear caches and run the smoke tests.
7. Reconcile any orders/stock movements created during the failed window:
   - order status events must be present,
   - stock levels must still reconcile with the physical ledger (query in
     section 5),
   - invoice numbers must remain unique and never reused.
8. Record the incident, the rollback commit, the dump filename and the
   reconciliation result.

## 8. Font and attribution preservation

- `resources/fonts/hind-siliguri/` (both TTF files, `OFL.txt` and `README.md`)
  is part of every release archive and Git tag.
- Do not delete or replace the font without updating `THIRD-PARTY-NOTICES.md`
  and `docs/invoice-pdf-and-whatsapp.md`.
- The OFL requires the copyright/licence notice to travel with the font. A
  rollback or server migration must not drop `OFL.txt`.
- The mPDF licence notice must travel with any distribution that includes
  `vendor/mpdf/mpdf` (see `THIRD-PARTY-NOTICES.md`).

## 9. Pre-deployment backup checklist

- [ ] `git status` clean; `main` matches `origin/main`; release tag pushed.
- [ ] Database dump created, gzip-tested and checksummed.
- [ ] Dump copied off the cPanel account.
- [ ] `.env` backed up with `600` permissions, outside the web root.
- [ ] Application directory archived (or a release tarball kept).
- [ ] `storage/app/private` and `storage/app/public` archived.
- [ ] Restore test completed in a disposable database and stock reconciliation
      returned no rows.
- [ ] Last known-good commit/tag recorded.
- [ ] Rollback owner and decision point agreed.
- [ ] Cron entry and SMTP settings recorded without secrets in the log.

## 10. What this audit did not do

- No production or staging database was accessed.
- No dump was exported from a live server.
- No restore was executed against a live database.
- No cron job was added.
- No DNS or cPanel setting was changed.
