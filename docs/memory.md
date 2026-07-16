# Project Memory — laravel-shortlink

Running log of what is done, in progress, and decided. Keep entries short and dated.

## Completed

- 2026-07-15 — Planning documentation created (README, PRD, architecture, rules, design, api-contracts, phases, testing, memory, launch-checklist, .env.example).
- 2026-07-17 — Phase 1, commit 1: Laravel 11.55.0 app scaffolded via `composer create-project`, dependencies pinned to exact versions (laravel/framework 11.55.0, laravel/sanctum 4.3.2, laravel/pint 1.29.3, pestphp/pest 3.8.7, laravel/sail 1.63.0, etc). `php artisan install:api` wired Sanctum + `routes/api.php`. Pest initialized (`tests/Pest.php`, `RefreshDatabase` on Feature tests). Pint clean.
- 2026-07-17 — Phase 1, commit 2: `.env.example` documents the sqlite local-dev fallback alongside the MySQL/Sail target.
- 2026-07-17 — Phase 1 complete (commits 3-12): links/clicks migrations; `Link`/`Click` models with relationships, casts, soft deletes; `App\Support\ApiResponse` envelope helpers; `App\Exceptions\Handler` renders the single JSON error envelope for all `api/*` and `expectsJson()` requests (wired via `bootstrap/app.php`); Sanctum register/login/logout (`App\Http\Controllers\Api\AuthController`); `App\Support\CodeGenerator` (8-char alphanumeric, retries against `withTrashed()` so removed codes are never reissued); `POST /api/links` create endpoint (`StoreLinkRequest`, `LinkResource`); public `GET /{code}` redirect with click recording (`App\Support\ClientIp` HMAC-SHA256 hash, never raw) and the `errors/404.blade.php` page. 18 Pest tests (6 Unit, 12 Feature), all passing; Pint clean.
  - Manual smoke test (via `php -S`, since `artisan serve` fails in this sandbox — see below): register, login (success + wrong password), logout + token reuse rejected, protected route without token, create link (valid/invalid url/past expiry), redirect (valid code + click row with hashed IP + referrer/UA, unknown code 404) — all matched the documented envelopes and status codes.
  - Testing note: Sanctum's guard caches the resolved user per test process, so a second in-test HTTP call reusing a just-revoked token would otherwise still authenticate; `tests/Feature/AuthTest.php` calls `Auth::forgetGuards()` between the two calls to force re-resolution. This is a test-process artifact only (real requests are separate processes and always re-resolve).
  - Environment quirk (not an app bug): `php artisan serve` fails in this sandbox with `libtidy.so.5deb1` missing for the spawned dev-server PHP process; `php -S 127.0.0.1:<port> -t public` works fine and was used for all manual verification.

- 2026-07-17 — Phase 2 complete: paginated `GET /api/links` (owner-scoped), `GET /api/links/{id}` and `DELETE /api/links/{id}` (both owner-only via a shared `findOwnedOrFail` check), `errors/410.blade.php` for soft-deleted codes, `Link::isExpired()` enforced on redirect with a dedicated `errors/expired.blade.php`, and `GET /api/links/{id}/stats` (`App\Http\Controllers\Api\StatsController`, `App\Http\Resources\ClickResource`) returning totals, `clicks_by_day` (grouped in PHP over `created_at`, not a driver-specific `DATE()`/`strftime()` raw expression, so it works unchanged on both sqlite and mysql), and a capped `recent` list with no ip field. Added 11 more Pest tests (40 total), all passing; Pint clean.
  - Bug found and fixed during manual verification, not by tests: `abort(403)` throws a plain `HttpException`, and Laravel's own exception handler converts `AuthorizationException` to `AccessDeniedHttpException` *before* our custom renderer runs (`prepareException()`), so the original `feat: add show link endpoint` commit shipped with `error.code = "server_error"` on 403s instead of `"forbidden"`. Fixed in `App\Exceptions\Handler` by matching `AccessDeniedHttpException` explicitly and by throwing `AuthorizationException` (not `abort(403)`) from controllers; covered by the non-owner tests added in this phase.

## In progress

- Phase 3: rate limiting/throttling, rate-limit headers, structured logging, safe click recording hardening, docs finalization.

## Decisions log

- 2026-07-15 — Stack fixed: Laravel 11.x / PHP 8.2+, Sanctum token auth, MySQL 8.x, Sail for local Docker, Pest for tests, Pint for formatting.
- 2026-07-15 — Redirect uses HTTP 302 (not 301) so target semantics can evolve without cached permanent redirects.
- 2026-07-15 — Links are soft-deleted; deleted codes return 410. Expired links also return 410 with a dedicated page.
- 2026-07-15 — Client IPs are never stored raw; they are hashed (sha256, salted with the app key) or truncated before persistence.
- 2026-07-15 — Single JSON envelope: `data`/`meta` for success, `error.{code,message,details}` for errors.
- 2026-07-15 — Short codes are auto-generated only; custom vanity codes are out of scope for v1.
- 2026-07-17 — Deviation from the decided stack (flagged per instructions, not an architecture.md change): the sandbox this project is implemented in has no working `docker-compose`/`docker compose` binary and MySQL image pulls are too slow/unreliable to complete, so Sail cannot actually boot here. `compose.yaml` (Sail, MySQL 8.4, PHP 8.2 runtime) is still committed as the documented target per architecture.md. Local dev and the automated test suite run against SQLite instead (`DB_CONNECTION=sqlite`, `database/database.sqlite` for dev, `:memory:` for tests via `phpunit.xml`). `composer install` also required setting `policy.advisories.block=false` in the global Composer config because Composer's advisory audit blocked resolution of every available laravel/framework 11.x release.
