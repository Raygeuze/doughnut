<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Docker quickstart

This project ships with a Docker setup inspired by `../kickpush`.

1. Build and start containers:

```bash
docker compose --env-file .env.docker up -d --build
```

2. Generate app key if needed:

```bash
docker compose --env-file .env.docker exec web php artisan key:generate
```

3. Run migrations:

```bash
docker compose --env-file .env.docker exec web php artisan migrate
```

Services:

- App (Traefik): http://doughnut.localhost
- Mailhog UI: http://localhost:8025
- MariaDB: localhost:33060

Optional Vite dev server:

```bash
docker compose --env-file .env.docker --profile dev up -d vite
```

Default Vite host port is `5175` (set by `VITE_PORT` in `.env.docker`).

## Outreach workflow (fraud-warning)

This app includes a compliance-focused outreach module that:

- classifies leads as `eligible`, `ineligible`, or `unknown`
- enforces one recipient per domain per campaign
- applies suppression and cooldown gates before queueing and before sending
- provides signed unsubscribe links with domain-level suppression propagation

Set defaults in `.env`:

```bash
OUTREACH_POLICY_VERSION=v1
OUTREACH_COOLDOWN_DAYS=30
OUTREACH_DOMAIN_SUPPRESSION_PROPAGATION_ENABLED=true
```

Suggested command flow:

```bash
# 1) Create schema + seed settings
php artisan migrate
php artisan db:seed

# 2) Ingest evidence-backed lead
php artisan outreach:ingest-lead "accounts@example.com" "https://example.com/contact" --target_domain=example.com --snippet="Contact accounts@example.com for billing"

# 3) Re-run eligibility for all leads (or one lead)
php artisan outreach:recalculate-eligibility

# 4) Create campaign
php artisan outreach:create-campaign "Fraud Warning Oct" "Important fraud warning" "We are sending this notice to warn about registry-style invoice scams."

# 5) Build one-per-domain queue
php artisan outreach:build-queue 1

# 6) Send campaign with final gate checks
php artisan outreach:send-campaign 1
```

Admin UI is available at:

- `/admin/outreach` dashboard
- `/admin/outreach/leads` lead evidence ingest + eligibility management
- `/admin/outreach/campaigns` campaign creation, queue build, and send actions

## Stage 1 registry seed automation

You can automate discovery of source datasets and ABR seed records:

```bash
# Discover ABN-related datasets from data.gov.au and persist metadata.
php artisan outreach:discover-datasets abn --rows=25

# Dry run if you only want to preview.
php artisan outreach:discover-datasets "abn lookup web services" --rows=10 --dry-run

# Fetch ABR seed businesses by postcode (requires ABR_AUTHENTICATION_GUID).
php artisan outreach:seed-from-abr --postcode=2000

# Or fetch ABR seed businesses by name.
php artisan outreach:seed-from-abr --name="plumbing" --name-postcode=3000
```

The ABR command persists candidates into `registry_seed_businesses` with full raw payload for traceability.

You can convert discovered datasets into canonical seed business records:

```bash
# 1) Expand registry_datasets into registry_dataset_resources.
php artisan outreach:discover-resources

# 2) Create ingest jobs for CSV resources using parser profile abn_csv_v1.
php artisan outreach:discover-resources --create-jobs --profile=abn_csv_v1 --formats=CSV --max-size-mb=25

# 3) Materialize pending ingest jobs into registry_seed_businesses.
php artisan outreach:materialize-seeds

# Optional preview and safety controls.
php artisan outreach:materialize-seeds --job-id=1 --limit=500 --dry-run
```

If remote TLS connections are unstable, keep `--max-size-mb` low for initial runs and process larger resources later in smaller batches.

## Domain discovery and contact crawling

After seeding businesses (from ABR or datasets), run:

```bash
# 1) Discover likely website domains for pending seed businesses.
php artisan outreach:discover-domains --limit=200

# 2) Crawl resolved domains for public contact emails.
php artisan outreach:crawl-contacts --limit=50
```

The crawl pipeline writes:

- domain candidates to `registry_business_domains`
- crawl traces to `registry_crawl_runs`
- discovered emails into `leads` + `lead_sources` with eligibility recalculation

## Renewal-window delivery timing

Campaign queue and send gates now enforce a renewal timing window so warnings are sent near expected renewal periods.

Lead records can carry:

- `next_business_renewal_at`
- `next_domain_renewal_at`
- `renewal_signal_source`
- `renewal_confidence_score`

The queue/send gate blocks records outside the configured window (or missing signals if strict mode is enabled).

You can override this per campaign:

- `enforce_renewal_window=true`: renewal-window gate applies
- `enforce_renewal_window=false`: campaign can run as any-time full outreach

Environment defaults:

```dotenv
OUTREACH_RENEWAL_WINDOW_ENABLED=true
OUTREACH_RENEWAL_WINDOW_LEAD_DAYS=30
OUTREACH_RENEWAL_WINDOW_LAG_DAYS=14
OUTREACH_RENEWAL_WINDOW_ALLOW_WITHOUT_SIGNAL=false
```

## Compliance controls

Two agreement-alignment controls are built in:

- ABR withdrawal purge action in admin UI:
	- Go to `/admin/outreach/leads`
	- Use "ABR Withdrawal Purge" with ABN or source record ID and a reason
	- Matching ABR seed records are deleted and audit trail entries are written

- Immutable source/transform history logging:
	- Ingest and ABR seeding operations write append-only audit entries to `outreach_audit_logs`
	- View recent entries in `/admin/outreach` under "Immutable Audit Log"

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
