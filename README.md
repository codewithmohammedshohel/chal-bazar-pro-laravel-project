# Chal Bazar Pro (Laravel)

Laravel 13 + Livewire 4 migration of the Chal Bazar rice & grocery shop.
The legacy React/Firebase application lives in a separate repository and is
not modified by this project.

## Stack

- PHP 8.3+ (verified locally on PHP 8.4)
- Laravel 13
- Livewire 4 with custom Blade authentication
- Tailwind CSS 4 + Vite

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Configure the database and mail transport in `.env`. Never commit `.env`.

## Authentication and RBAC

Roles: `superadmin`, `admin`, `staff`, `customer`.
Account statuses: `active`, `suspended`, `blocked`.

- Registration always creates an `active` `customer`; role/status are never
  taken from submitted values.
- Suspended/blocked accounts cannot log in or keep an authenticated session.
- Permission keys and role defaults are preserved from the legacy app in
  `app/Support/Permissions.php`.
- Per-user overrides live in `user_permission_overrides` (`granted` = true/false).
  An explicit override always wins over the role default.
- An active `superadmin` bypasses permission checks for known catalogue keys.
- Gates are registered in `AppServiceProvider`; routes/actions can use
  `Gate::allows()`, `can` middleware or the `permission:` middleware alias.

## Provisioning the first owner (CLI only)

There is no default owner account, no default password, and no web route that
promotes users. Run the interactive command on the target environment:

```bash
php artisan app:provision-owner owner@example.com --name="Store Owner"
```

The command:

- validates the email address;
- requires a password of at least 12 characters with upper/lowercase letters,
  a number and a symbol, entered twice without echo;
- refuses to overwrite an existing account unless `--force` is supplied;
- refuses to run in the `production` environment unless `--allow-production`
  is explicitly passed and confirmed;
- never prints the password.

After the owner signs in, further role/permission changes must be performed by
authorised administration code (not implemented in this milestone).

## Testing

```bash
php artisan test
```

Tests use an isolated in-memory SQLite database and fake mail/notifications.
No real email is sent and no production database is touched.

The MySQL suite runs against a disposable database. Run the additive
migrations first, then override the connection:

```bash
php artisan migrate --force
DB_CONNECTION=mysql DB_DATABASE=chal_bazar_mysql_test \
  DB_USERNAME=... DB_PASSWORD=... php artisan test
```

See `docs/inventory-integrity.md` for stock accounting, cumulative return
limits, reservation expiry, the scheduler command and the real multi-process
MySQL concurrency tests.

## Inventory integrity

- Returns are linked to real order items, capped against the historical
  dispatched weight, recorded in `order_return_batches` / `order_returns`, and
  restocked exactly once.
- Pending reservations expire after `ORDER_RESERVATION_TTL_HOURS` (default 24)
  through `php artisan orders:expire-reservations` and the scheduler.
- All stock arithmetic uses integer 1/10,000 kg units.

## Repository hygiene

The following are intentionally excluded from Git: `.env`, SQLite databases,
`vendor/`, `node_modules/`, `public/build/`, generated Livewire assets,
logs and caches.

## Known limitations (this milestone)

- SMTP is not configured, so password-reset emails are only verified with fakes.
- Email verification is not enabled in the current design.
- OAuth/phone OTP from the Firebase app is not implemented.
- PDF invoice generation, WhatsApp notifications, payment gateways and cPanel
  deployment are deferred to later milestones.
