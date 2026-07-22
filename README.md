# laravel-shortlink

A URL shortener REST API built with Laravel. Authenticated users create short links backed by unique codes; visiting a short code issues an HTTP redirect to the target URL and records a click (referrer, user agent, hashed IP); link owners view per-link click analytics. The API uses token authentication via Laravel Sanctum, a consistent JSON response envelope, request throttling for rate limiting, and minimal accessible HTML pages for 404, 410 (gone), and expired-link redirect states.

## Features

- Token-based authentication with Laravel Sanctum (register, login, logout, issue/revoke tokens).
- Create short links with an auto-generated unique code, a validated target URL, and an optional expiry timestamp.
- Public redirect endpoint: `GET /{code}` issues a 302 redirect to the target URL.
- Click recording on every successful redirect: referrer, user agent, and a hashed/truncated IP.
- Per-link analytics: total clicks, unique-ish counts, recent clicks, and time-bucketed summaries.
- List, show, and delete links scoped to the authenticated owner.
- Consistent JSON success and error envelopes across all API endpoints.
- Rate limiting via throttle middleware on API and redirect routes with standard rate-limit headers.
- Accessible minimal Blade pages for 404 (unknown code), 410 (deleted/gone), and expired states.

## Tech stack

- PHP 8.2+ / Laravel 12.x
- Laravel Sanctum (API token auth)
- MySQL 8.x
- Laravel Sail (Docker: app + MySQL)
- Pest (feature + unit tests) running on PHPUnit
- Laravel Pint (PSR-12 formatting)

## Prerequisites

- Docker Desktop (or a compatible Docker engine) running locally.
- Git.
- Optional for the one-time bootstrap only: PHP 8.2+ and Composer on the host, or use the official Composer Docker image to install dependencies before the first Sail boot.

## Install

```bash
# 1. Clone
git clone <repo-url> laravel-shortlink
cd laravel-shortlink

# 2. Copy environment file and fill values
cp .env.example .env

# 3. Install PHP dependencies (first run, via the Composer container so no host PHP is required)
docker run --rm -v "$(pwd)":/opt -w /opt laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs

# 4. Start the Sail stack (app + MySQL)
./vendor/bin/sail up -d

# 5. Generate the app key
./vendor/bin/sail artisan key:generate

# 6. Run migrations
./vendor/bin/sail artisan migrate
```

### Local dev without Docker (SQLite fallback)

If Docker/Sail is not available, the app runs against SQLite instead of MySQL. This is a
local-only fallback; the deployed target remains MySQL via Sail (see `docs/architecture.md`
and the decisions log in `docs/memory.md`).

```bash
cp .env.example .env
composer install
touch database/database.sqlite
# In .env, set DB_CONNECTION=sqlite (leave DB_DATABASE unset or point it at the file above)
php artisan key:generate
php artisan migrate
php -S 127.0.0.1:8000 -t public
```

## Run

```bash
# Start the containers (if not already running)
./vendor/bin/sail up -d

# The API is served at APP_URL (default http://localhost)
# Tail logs
./vendor/bin/sail logs -f

# Stop
./vendor/bin/sail down
```

## Test

```bash
# Run the full test suite (Pest on PHPUnit)
./vendor/bin/sail artisan test

# Check formatting (PSR-12)
./vendor/bin/sail bin pint --test

# Auto-fix formatting
./vendor/bin/sail bin pint
```

Without Sail, drop the `./vendor/bin/sail` prefix and run `php artisan test`,
`./vendor/bin/pint --test`, etc. directly; the test suite runs against an in-memory
SQLite database regardless of the driver used for local dev (see `phpunit.xml`).

Build and tests must pass before any feature is considered done. See `docs/testing.md`.

## Structure

```
laravel-shortlink/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # Auth, Link, Stats, Redirect controllers
│   │   ├── Requests/         # Form requests (server-side validation)
│   │   ├── Resources/        # API resources (JSON shaping)
│   │   └── Middleware/       # Envelope / throttle helpers if needed
│   ├── Models/               # User, Link, Click
│   └── Support/              # Code generator, IP hashing helpers
├── database/migrations/      # users, links, clicks, personal_access_tokens
├── resources/views/errors/   # 404, 410, expired Blade pages
├── routes/                   # api.php, web.php
├── tests/                    # Feature + Unit (Pest)
└── docs/                     # PRD, architecture, rules, design, api-contracts,
                              # phases, testing, memory, launch-checklist
```

## License

License: MIT
