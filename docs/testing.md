# Testing — laravel-shortlink

## Strategy

- **Automated first, manual second.** Every feature ships with automated tests. Manual QA (see the phase checklists) covers browser behavior of redirect/HTML pages and rate-limit headers that are awkward to assert.
- **Pest on PHPUnit.** Use Pest's expressive syntax; the underlying engine is PHPUnit, so both `pest` and `artisan test` work. Prefer feature tests for HTTP behavior and unit tests for isolated helpers.
- **Test database.** Use a dedicated test connection with `RefreshDatabase` so each test runs against a clean schema. Never run tests against the development database.
- **Factories** for `User`, `Link`, `Click` drive setup; do not hand-build rows in tests.

### What to cover

Feature tests:
- Auth: register success/validation, login success/bad credentials, logout revokes token, protected route without token → 401.
- Create link: valid → 201 with unique code and correct `short_url`; invalid/non-http URL → 422; past `expires_at` → 422.
- Redirect: active → 302 to target and one click row (hashed IP, not raw); unknown → 404; deleted → 410; expired → expired page.
- List/show/delete: owner-scoping, pagination, empty state, non-owner → 403, missing → 404, delete → 204 then 410.
- Stats: totals and `clicks_by_day` match seeded clicks; `recent` exposes no IPs; owner-only.
- Rate limiting: exceeding a limit → 429 with the error envelope and rate-limit headers.
- Envelope shape: success and error responses match the documented JSON exactly.

Unit tests:
- `CodeGenerator`: produces unique codes of the expected length/alphabet; retries on collision.
- `ClientIp`: never returns the raw IP; same IP → same hash; different IP → different hash.

## Exact commands

Run inside the Sail containers (they wrap Docker; run from the project root).

```bash
# Full test suite
./vendor/bin/sail artisan test

# Equivalent via Pest directly
./vendor/bin/sail pest

# A single file
./vendor/bin/sail artisan test tests/Feature/RedirectTest.php

# Filter by test name
./vendor/bin/sail artisan test --filter=redirects_active_link

# Formatting check (must pass; PSR-12 via Pint)
./vendor/bin/sail bin pint --test

# Auto-fix formatting
./vendor/bin/sail bin pint
```

First-time or CI setup (no host PHP required):

```bash
docker run --rm -v "$(pwd)":/opt -w /opt laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

## Definition of "done" for a feature

A feature is not done until all of the following pass:

1. `./vendor/bin/sail bin pint --test` — no style violations.
2. `./vendor/bin/sail artisan test` — the full suite is green (new tests included).
3. The feature's manual checklist items in `docs/phases.md` pass.

Build and tests must pass before a feature is committed. One commit per feature, in the order listed in `docs/phases.md`.
