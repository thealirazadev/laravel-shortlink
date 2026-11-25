# Testing - laravel-shortlink

## Strategy

- **Automated first, manual second.** Every feature ships with automated tests. Manual checks
  cover what is awkward to assert in a test: how the redirect and HTML error pages behave in a
  real browser, and rate-limit headers against a running server.
- **Pest on PHPUnit.** Pest's syntax on the PHPUnit engine, so both `pest` and `artisan test` work.
  Feature tests cover HTTP behavior; unit tests cover isolated helpers.
- **Test database.** `phpunit.xml` overrides `DB_CONNECTION`/`DB_DATABASE` to in-memory SQLite and
  feature tests use `RefreshDatabase`, so every test runs against a clean schema and never touches
  the development database, whichever driver local dev uses.
- **Factories** for `User`, `Link` and `Click` drive setup; rows are not hand-built in tests.

### What is covered

Feature tests:
- Auth: register success and validation, login success and a safe message on a wrong password,
  logout revokes the token so reuse yields 401, a protected route without a token yields 401.
- Create link: valid request returns 201 with a unique code and `short_url`; non-http URL, past
  `expires_at` and an over-length URL return 422; duplicate create requests get distinct codes.
- Redirect: an active link redirects with 302 and records exactly one click with a hashed IP;
  unknown code 404; deleted link 410; expired link 410 with the expired page; a failed click insert
  still redirects and logs the failure; an over-long user agent is truncated, not fatal.
- List, show, delete: owner scoping and pagination, the empty list, non-owner 403, missing 404,
  soft delete followed by 410 on the code.
- Stats: totals and `clicks_by_day` match recorded clicks, `recent` exposes no IPs, non-owner 403,
  missing link 404.
- Rate limiting: exceeding the auth limit returns 429 with the error envelope and rate-limit
  headers; a different IP is not affected.

Unit tests:
- `CodeGenerator`: expected length and alphabet; retries until a code is free.
- `ClientIp`: never returns the raw IP; the same IP hashes the same; different IPs hash
  differently; a null IP stays null.

## Exact commands

Inside the Sail containers (run from the project root):

```bash
# Full test suite
./vendor/bin/sail artisan test

# Equivalent via Pest directly
./vendor/bin/sail pest

# A single file
./vendor/bin/sail artisan test tests/Feature/RedirectTest.php

# Filter by test name
./vendor/bin/sail artisan test --filter="hashed ip"

# Formatting check (must pass; PSR-12 via Pint)
./vendor/bin/sail bin pint --test

# Auto-fix formatting
./vendor/bin/sail bin pint
```

First-time setup with Sail (no host PHP required):

```bash
docker run --rm -v "$(pwd)":/opt -w /opt laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

### Without Sail

Drop the `./vendor/bin/sail` prefix and run the same commands against host PHP and Composer
(`php artisan test`, `./vendor/bin/pest`, `./vendor/bin/pint --test`). The suite runs on in-memory
SQLite either way, so this does not change what the tests verify.

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request to `main`, on a PHP 8.2, 8.3 and 8.4
matrix, without Sail. After `composer install` it copies `.env.example` to `.env`, runs
`php artisan key:generate --force`, and then runs, in order:

1. `./vendor/bin/pint --test` - no style violations.
2. `php artisan test` - the full suite on in-memory SQLite.

## Definition of "done" for a feature

1. `./vendor/bin/pint --test` passes.
2. `php artisan test` passes, new tests included.
3. The relevant manual checks pass.
