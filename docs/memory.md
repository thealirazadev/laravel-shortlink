# Project Memory — laravel-shortlink

Running log of what is done, in progress, and decided. Keep entries short and dated.

## Completed

- 2026-07-15 — Planning documentation created (README, PRD, architecture, rules, design, api-contracts, phases, testing, memory, launch-checklist, .env.example).

## In progress

- _(nothing yet)_

## Decisions log

- 2026-07-15 — Stack fixed: Laravel 11.x / PHP 8.2+, Sanctum token auth, MySQL 8.x, Sail for local Docker, Pest for tests, Pint for formatting.
- 2026-07-15 — Redirect uses HTTP 302 (not 301) so target semantics can evolve without cached permanent redirects.
- 2026-07-15 — Links are soft-deleted; deleted codes return 410. Expired links also return 410 with a dedicated page.
- 2026-07-15 — Client IPs are never stored raw; they are hashed (sha256, salted with the app key) or truncated before persistence.
- 2026-07-15 — Single JSON envelope: `data`/`meta` for success, `error.{code,message,details}` for errors.
- 2026-07-15 — Short codes are auto-generated only; custom vanity codes are out of scope for v1.
