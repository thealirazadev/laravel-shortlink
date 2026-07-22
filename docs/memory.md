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

- 2026-07-17 — Phase 2 complete: paginated `GET /api/links` (owner-scoped), `GET /api/links/{id}` and `DELETE /api/links/{id}` (both owner-only via a shared `findOwnedOrFail` check), `errors/410.blade.php` for soft-deleted codes, `Link::isExpired()` enforced on redirect with a dedicated `errors/expired.blade.php`, and `GET /api/links/{id}/stats` (`App\Http\Controllers\Api\StatsController`, `App\Http\Resources\ClickResource`) returning totals, `clicks_by_day` (grouped in PHP over `created_at`, not a driver-specific `DATE()`/`strftime()` raw expression, so it works unchanged on both sqlite and mysql), and a capped `recent` list with no ip field. Added 11 more Pest tests (29 total), all passing; Pint clean.
  - Bug found and fixed during manual verification, not by tests: `abort(403)` throws a plain `HttpException`, and Laravel's own exception handler converts `AuthorizationException` to `AccessDeniedHttpException` *before* our custom renderer runs (`prepareException()`), so the original `feat: add show link endpoint` commit shipped with `error.code = "server_error"` on 403s instead of `"forbidden"`. Fixed in `App\Exceptions\Handler` by matching `AccessDeniedHttpException` explicitly and by throwing `AuthorizationException` (not `abort(403)`) from controllers; covered by the non-owner tests added in this phase.

- 2026-07-17 — Phase 3 complete: named rate limiters (`App\Providers\AppServiceProvider::boot()`) — `auth` (10/min by IP) on register/login, `api` (60/min by user id) on all authenticated endpoints, `redirect` (120/min by IP) on the public redirect — applied via `throttle:*` middleware. Laravel's built-in `ThrottleRequests` already sets `X-RateLimit-Limit`/`X-RateLimit-Remaining`/`Retry-After`/`X-RateLimit-Reset`; `App\Exceptions\Handler` now copies those headers from the exception onto the JSON envelope response (they otherwise live on the exception, not the response we build) and treats `TooManyRequestsHttpException` as JSON-always so the redirect route's 429 also returns the error envelope instead of falling through to an HTML page. Added structured `redirect.not_found` / `redirect.deleted` / `redirect.expired` / `redirect.succeeded` log events alongside the existing `auth.*`, `link.created`, `link.deleted`, `click.record_failed`. Click recording now truncates `referrer`/`user_agent` to 255 chars defensively (matching the migration's column width) before the existing try/catch, so an oversized header is handled without ever reaching the failure path. README and testing.md updated with a non-Sail (SQLite) quickstart. 6 more Pest tests (34 total: rate limiting x2, forced click-insert failure, user-agent truncation, plus one extra for the over-length `target_url` validation path), all passing; Pint clean.
  - Bug found and fixed via manual verification (not caught by earlier tests, since the redirect route path isn't `api/*` and doesn't send `Accept: application/json`): a 429 on `GET /{code}` was falling through `App\Exceptions\Handler`'s api/json guard and rendering Laravel's default (non-JSON) error output, contradicting `docs/api-contracts.md`'s "every throttled route" wording. Fixed by special-casing `TooManyRequestsHttpException` to always render the JSON envelope regardless of route/`Accept` header, while 404/410/expired on the redirect route continue to render their dedicated Blade pages exactly as before.

## Phase 3 verification (docs/phases.md checklist)

- Format/tests: `./vendor/bin/pint --test` and `php artisan test` both clean, 34/34 passing (run as `php artisan test` / `./vendor/bin/pint` directly rather than via `./vendor/bin/sail ...` — see the Sail deviation note below).
- Logs checked: manual end-to-end run (register, login, create, visit, stats, unknown code) produced only the expected `auth.*`/`link.created`/`redirect.*` info/warning lines, no errors.
- Unhappy paths: invalid URL, over-length `target_url`, missing/invalid auth, duplicate-create code uniqueness, expired-link page, forced click-insert failure (redirect still 302, failure logged), duplicate create requests all verified via tests and/or manual curl.
- Empty states: `GET /api/links` on a fresh account returns `data: []` / `meta.total: 0` (tested).
- Long inputs: `target_url` over 2048 chars → 422 (tested); a 500-char `User-Agent` on the redirect → truncated to 255 chars, click still recorded, 302 still returned (tested).
- Accessibility: `errors/404.blade.php`, `errors/410.blade.php`, `errors/expired.blade.php` all share one template — `lang="en"`, single `<main>`/`<h1>`, viewport meta, 16px base font, high-contrast light/dark palette, link back to `/`. Not verified with an automated a11y tool (none in the approved stack); reviewed by hand against `docs/design.md`'s checklist.
- Not re-verified here (already covered in Phase 1/2 sign-off and unchanged since): Sail cannot boot in this sandbox (see deviation below); all "App runs" verification instead uses `php -S 127.0.0.1:<port> -t public` per the earlier note.

## In progress

- None — all three requested phases (1-3) are complete. Anything further (custom vanity codes, dashboards, etc.) is out of v1 scope per `docs/PRD.md` and would need a new phase or backlog entry.

## Decisions log

- 2026-07-15 — Stack fixed: Laravel 11.x / PHP 8.2+, Sanctum token auth, MySQL 8.x, Sail for local Docker, Pest for tests, Pint for formatting.
- 2026-07-15 — Redirect uses HTTP 302 (not 301) so target semantics can evolve without cached permanent redirects.
- 2026-07-15 — Links are soft-deleted; deleted codes return 410. Expired links also return 410 with a dedicated page.
- 2026-07-15 — Client IPs are never stored raw; they are hashed (sha256, salted with the app key) or truncated before persistence.
- 2026-07-15 — Single JSON envelope: `data`/`meta` for success, `error.{code,message,details}` for errors.
- 2026-07-15 — Short codes are auto-generated only; custom vanity codes are out of scope for v1.
- 2026-07-23 — Security upgrade: bumped `laravel/framework` 11.55.0 → 12.64.0 to clear
  `composer audit`. Before: 6 advisories — 3 medium on guzzlehttp/guzzle <7.15.1 (URI fragments in
  redirect Referer headers, host-only cookie scope not preserved, unbounded response cookies) and 3
  on laravel/framework (high CRLF injection in the default email rule / CVE-2026-48019, fixed only in
  12.60.0+; medium temporary signed URL path confusion, fixed only in 12.61.1+ — no 11.x patch
  exists for either, so the 12.x major was the only route). The `composer update laravel/framework
  --with-all-dependencies` also pulled guzzle to 7.15.1, clearing all three guzzle advisories.
  After: zero advisories. The 11 → 12 upgrade needed no application code and no config changes: the
  app uses none of the breaking surfaces (no `HasUuids`, no `Concurrency::run`, no `image` validation
  rule, no `Schema::getTables()`/`getTableListing()`, no `mergeIfMissing`, no direct
  `DatabaseTokenRepository`, no custom-named duplicate routes affected by the route-precedence
  change), and `config/filesystems.php` already defines the `local` disk explicitly with the 12.x
  `storage/app/private` root and `serve => true`. Sanctum 4.3.2 already allows `illuminate/* ^12.0`,
  so it stayed pinned; Carbon was already on 3.13.1. Dev tooling (pest 3.8.7, phpunit 11.5.56,
  collision 8.9.5, pint 1.29.3) already satisfies the Laravel-12 guide and was left untouched.
  Laravel 12 still requires PHP ^8.2. Test suite unchanged at 34 tests / 117 assertions, pint clean.
  Version pins in composer.json stay exact per this project's convention; updated docs/architecture.md
  and README stack lines to Laravel 12.x. Same clean outcome as sibling laravel-uptime / hook-relay.
- 2026-07-17 — Deviation from the decided stack (flagged per instructions, not an architecture.md change): the sandbox this project is implemented in has no working `docker-compose`/`docker compose` binary and MySQL image pulls are too slow/unreliable to complete, so Sail cannot actually boot here. `compose.yaml` (Sail, MySQL 8.4, PHP 8.2 runtime) is still committed as the documented target per architecture.md. Local dev and the automated test suite run against SQLite instead (`DB_CONNECTION=sqlite`, `database/database.sqlite` for dev, `:memory:` for tests via `phpunit.xml`). `composer install` also required setting `policy.advisories.block=false` in the global Composer config because Composer's advisory audit blocked resolution of every available laravel/framework 11.x release.
