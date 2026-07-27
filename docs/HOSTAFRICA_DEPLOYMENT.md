# HOSTAFRICA shared-host deployment

## Confirm before launch

- PHP 8.3+ with `bcmath`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, and `zip`.
- MySQL 8 with `utf8mb4`, HTTPS, one-minute cron, and permission to keep the application above `public_html`.
- The server’s physical country, backup/restore procedure, public callback reachability, and available disk/inode quotas.
- ODPC registration position, controller-processor terms, subprocessor register, privacy/waiver text, DPIA screen, incident runbook, and Kenyan legal/tax review.

## Filesystem layout

```text
/home/account/gymflow/        Laravel root, .env, vendor, storage
/home/account/public_html/    contents of gymflow/public only
```

Point the domain document root at `gymflow/public` where the panel permits it. Otherwise copy only the public directory’s contents to `public_html` and adjust its `index.php` paths to the application root. Never expose `.env`, `vendor`, `storage`, database dumps, or deployment artifacts.

## Environment

Set MySQL, mail, Daraja, and Africa’s Talking variables from `.env.example`. Additionally:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
DB_CONNECTION=mysql
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Generate a long random `APP_KEY` and a separate random `MPESA_DEPLOYMENT_TOKEN`. Register this callback with every supported merchant connection:

```text
https://your-domain.example/webhooks/mpesa/express/{MPESA_DEPLOYMENT_TOKEN}
```

Register `https://your-domain.example/webhooks/africastalking/sms` for delivery reports.

## Release

Build locally with pinned lockfiles:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
powershell -ExecutionPolicy Bypass -File scripts/build-release.ps1
```

Back up files and MySQL, upload/extract the artifact, then use a temporary one-time cron or panel terminal:

```text
php artisan down --retry=60
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan queue:restart
php artisan up
php artisan about
```

Run smoke checks for `/up`, login, a public gym page, tenant CSS/manifest, member search, and a low-value STK transaction. Keep the prior code artifact. Database migrations must remain backward-compatible because code rollback does not reverse production data.

## Permanent cron

Run once per minute:

```text
* * * * * cd /home/account/gymflow && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

The application schedule runs bounded queue work (`--stop-when-empty --max-time=50`) and writes a heartbeat. Alert on a heartbeat older than two minutes, failed jobs, queue age above two minutes, unresolved callbacks, unwritable storage, database failure, disk quota, p95 response above two seconds, or sustained hosting utilization over 70%.

Move to a managed VPS before the eleventh active gym or when the documented concurrency, latency, queue-lag, or resource thresholds persist.
