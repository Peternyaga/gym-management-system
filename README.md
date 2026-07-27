# GymFlow Kenya

A Kenya-first, multi-tenant gym management SaaS built as a Laravel 13 modular monolith with Inertia 3, React 19, TypeScript, Tailwind 4, Fortify, MySQL/SQLite, database queues, and an installable tenant PWA.

## Implemented product slice

- Tenant registration, 14-day trials, a main location, owner/staff/member roles, and route/query tenant guards.
- A versioned theme studio with separate light/dark palettes, controlled presets, WCAG contrast validation, draft/publish/rollback, immutable CSS, tenant manifests, branded auth/public pages, and safe WebP asset processing.
- Member records, verified Kenyan-phone self-registration, versioned consent records, membership plans, membership snapshots, issued invoices, and seeded operating data.
- Invoices, payments, allocations, encrypted merchant-configuration storage, STK gateway contract, Daraja adapter, idempotent callbacks, overpayment handling, and eTIMS-preparation CSV.
- Classes, capacity-safe member booking, FIFO waitlist promotion, rotating one-minute access tokens, duplicate check-in handling, and role-specific staff/member portals.
- Africa’s Talking SMS contract and delivery callbacks, scheduler heartbeat, stale-payment reconciliation, audit records, security headers, database-backed sessions/cache/queues, and platform tenant health.

The app deliberately labels receipts as ordinary payment receipts; it does not claim to issue an eTIMS tax invoice.

## Local setup

Requirements: PHP 8.3+, Composer 2, Node 22+, and either SQLite or MySQL 8.

```powershell
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/database.sqlite
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

Seeded local users use password `password`:

- Gym owner: `owner@gymflow.test`
- Platform admin: `admin@gymflow.test`

The demo tenant is available at `/g/pamoja-strength-club`.

## Verification

```powershell
composer test
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

For local machines where the default `php` is older than 8.3, place the PHP 8.3 directory first in `PATH` before invoking Composer or Vite; the Wayfinder build plugin runs `php artisan`.

## Production configuration

Use MySQL 8, HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, a random `MPESA_DEPLOYMENT_TOKEN`, production Daraja/Africa’s Talking credentials, and database-backed sessions/cache/queues. Never place `.env`, source, storage, or vendor metadata inside the web root.

See [HOSTAFRICA deployment](docs/HOSTAFRICA_DEPLOYMENT.md) and [implementation architecture](docs/ARCHITECTURE.md).
