# Launch Checklist — laravel-shortlink

Work top to bottom before going to production. Nothing here is checked until verified in the target environment.

## Environment & configuration

- [ ] Production `.env` created from `.env.example` with real values (no dummy data).
- [ ] `APP_KEY` generated for production.
- [ ] `APP_DEBUG=false` in production.
- [ ] `APP_URL` set to the real public host (short URLs build from this).
- [ ] `APP_ENV=production`.
- [ ] Database credentials (`DB_*`) point at the production MySQL instance and are stored securely (not in the repo).
- [ ] Config/route/view caches warmed (`config:cache`, `route:cache`, `view:cache`).

## Security

- [ ] No secrets committed; `.env` is git-ignored; only `.env.example` (dummy) is tracked.
- [ ] HTTPS enforced; HTTP redirects to HTTPS.
- [ ] Sanctum token flow verified end-to-end against production.
- [ ] Rate limits configured and active on auth, API, and redirect routes; `429` + rate-limit headers confirmed.
- [ ] All API input validated via Form Requests; no unvalidated endpoints.
- [ ] IP hashing/truncation confirmed — no raw IPs in the `clicks` table.

## Reliability & observability

- [ ] Error tracking / monitoring wired up (aggregated logs or an error tracker) and receiving events.
- [ ] Structured logs verified for `auth.*`, `link.created`, `redirect.*`, `click.record_failed`.
- [ ] Click-recording failures confirmed non-fatal (redirect still succeeds, failure logged).
- [ ] Database backups scheduled and a restore tested at least once.
- [ ] Migrations run cleanly on production (`migrate --force`); rollback path understood.

## Pages & responses

- [ ] 404 page (unknown code) returns HTTP 404 and renders correctly.
- [ ] 410 page (deleted link) returns HTTP 410 and renders correctly.
- [ ] Expired page returns the documented status and renders correctly.
- [ ] Generic 500 error path returns a safe page/JSON (no stack trace) with `APP_DEBUG=false`.
- [ ] All HTML pages checked on mobile (viewport, readable font, AA contrast, single-column, keyboard accessible).

## Quality gates

- [ ] `./vendor/bin/sail artisan test` (or CI equivalent) green.
- [ ] `./vendor/bin/sail bin pint --test` clean.
- [ ] `composer.lock` committed with pinned versions matching the deployed build.

## Project-specific

- [ ] Short-code collisions handled under load (unique constraint + retry verified).
- [ ] Redirect latency acceptable (indexed lookup on `links.code`).
- [ ] `clicks.created_at` indexed so `clicks_by_day` stats stay fast as data grows.
- [ ] Expiry logic verified against production timezone/UTC handling.
- [ ] Long `target_url` values persist without truncation errors.
