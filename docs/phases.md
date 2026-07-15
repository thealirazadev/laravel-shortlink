# Phases — laravel-shortlink

Phases are ordered smallest-useful-shippable first. Each phase ends green: app runs, tests pass, logs clean. One commit per feature/task, Conventional Commits, in the listed order.

---

## Phase 1 — Foundation, auth, and the create/redirect core

The smallest end-to-end slice: a user can register, log in, create a link, and have the code redirect while recording a click.

### Definition of done

- Sail stack (app + MySQL) boots; `.env` from `.env.example`; `APP_KEY` generated.
- Migrations for `users`, `personal_access_tokens`, `links`, `clicks` applied.
- Models `User`, `Link`, `Click` with relationships, `$fillable`, `$casts`, soft deletes on `Link`.
- Sanctum register/login/logout returning the success/error envelopes.
- `POST /api/links` creates an owner-scoped link with a unique generated code.
- `GET /{code}` redirects active links (302) and records exactly one click with hashed IP.
- Success and error envelopes implemented via shared helpers + exception handler.
- Pint clean; feature + unit tests for the above pass.

### Manual test checklist

- Register a user → receive token.
- Login → receive token; wrong password → 422 with safe message.
- Call `POST /api/links` without token → 401 envelope.
- Create a link with a valid URL → 201 with `short_url` and unique `code`.
- Visit the code in a browser/curl → 302 to target; a `clicks` row is created.
- Visit an unknown code → 404 HTML page.

### Commits

1. `chore: scaffold laravel app and sail services`
2. `chore: add env example and app config`
3. `feat: add links and clicks migrations`
4. `feat: add user link and click models with relationships`
5. `feat: add api response envelope helpers`
6. `feat: render single json error format in exception handler`
7. `feat: add sanctum register login logout endpoints`
8. `feat: add unique short code generator`
9. `feat: add create link endpoint`
10. `feat: add public redirect with click recording`
11. `feat: add 404 page for unknown code`
12. `test: cover auth create and redirect happy paths`

---

## Phase 2 — Link management and analytics

Owners can list, view, delete their links, and read analytics; expired/deleted edge states are served.

### Definition of done

- `GET /api/links` (paginated, owner-scoped), `GET /api/links/{id}` (owner-only), `DELETE /api/links/{id}` (soft delete → 410).
- `GET /api/links/{id}/stats` returns totals, `clicks_by_day`, and capped `recent` (no raw IPs).
- Expired links serve the expired HTML page; deleted links serve the 410 page.
- `expires_at` validated as a future datetime on create; expiry enforced on redirect.
- Authorization: non-owner → 403; missing id → 404.
- Tests cover list/show/delete, stats aggregation, expiry, and authorization.

### Manual test checklist

- List links → only caller's links, paginated; empty account → `data: []`.
- Show another user's link → 403; show missing id → 404.
- Delete a link → 204; visiting its code → 410 page.
- Create a link with `expires_at` in the past → 422; in the future then time-travel/expire → visiting → expired page.
- Read stats after several visits → totals and `clicks_by_day` match recorded clicks; `recent` shows no IPs.

### Commits

1. `feat: add paginated list links endpoint`
2. `feat: add show link endpoint with owner authorization`
3. `feat: add soft delete link endpoint`
4. `feat: serve 410 page for deleted links`
5. `feat: enforce link expiry on redirect`
6. `feat: serve expired page for expired links`
7. `feat: add per link stats endpoint`
8. `test: cover management stats expiry and authorization`

---

## Phase 3 — Hardening: rate limiting, logging, and polish

Production-readiness for the API surface.

### Definition of done

- Throttle middleware applied per `api-contracts.md` (auth by IP, API by user, redirect by IP) with `X-RateLimit-*` headers and 429 envelope.
- Structured logging for key events (`auth.*`, `link.created`, `redirect.*`, `click.record_failed`).
- Click recording wrapped so failures are logged and never break the redirect.
- Consistent handling verified for long inputs, duplicate submits, and empty states.
- README and testing docs reflect final commands; Pint clean; full suite passes.

### Manual test checklist

- Exceed the login limit → 429 with `rate_limited` and rate-limit headers.
- Exceed the redirect limit from one IP → 429; other IPs unaffected.
- Force a click-insert failure (e.g. simulate) → redirect still succeeds; a `click.record_failed` log entry appears.
- Submit a very long `target_url` → handled (422 or accepted per max) without a 500.
- Submit the same create request twice → two distinct links with distinct codes, no collision error.

### Commits

1. `feat: add throttling to auth api and redirect routes`
2. `feat: return rate limit headers and 429 envelope`
3. `feat: add structured logging for key events`
4. `feat: make click recording failure safe`
5. `docs: finalize readme and testing commands`
6. `test: cover rate limiting and safe click recording`

---

## Phase verification (run at the end of every phase)

- [ ] App runs: `./vendor/bin/sail up -d` boots with no errors; `./vendor/bin/sail logs` is clean.
- [ ] Tests pass: `./vendor/bin/sail artisan test` green.
- [ ] Format clean: `./vendor/bin/sail bin pint --test` reports no issues.
- [ ] Logs checked: no unexpected errors/warnings during a manual run.
- [ ] Unhappy paths exercised:
  - [ ] Invalid URL on create → 422 envelope (no 500).
  - [ ] Missing/invalid auth on protected route → 401 envelope.
  - [ ] Duplicate code generation under repeated creates → all unique, no collision error.
  - [ ] Expired link visit → expired page with correct status.
  - [ ] No network / DB hiccup during click recording → redirect still works, failure logged.
  - [ ] Duplicate submit of the same create → no crash, distinct links.
- [ ] Empty states: listing with no links returns `data: []` / `total: 0`.
- [ ] Long inputs: very long `target_url` and long user-agent handled without truncation errors or 500s.
- [ ] Accessibility: 404/410/expired pages have `lang`, `<h1>`, AA contrast, and read well on mobile.

## Backlog

_(empty — move out-of-scope ideas here with a one-line rationale)_
