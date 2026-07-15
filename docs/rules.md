# Engineering Rules — laravel-shortlink

These rules are binding for every change in this repository.

## Conventions

- **Framework patterns**: Use Laravel idioms. Controllers stay thin; validation lives in Form Requests; JSON shaping lives in API Resources; business logic lives in models or small `app/Support` classes. Do not put query logic in routes or middleware.
- **Preferred libraries**: Stick to the framework and the stack already chosen — Sanctum for auth, Eloquent for data access, Pest for tests, Pint for formatting. Do not introduce query builders, DTO libraries, or repositories for v1.
- **What to avoid**: No raw SQL string concatenation, no `DB::raw` with user input, no global helpers for domain logic, no service-locator hacks, no fat controllers, no logic in Blade beyond simple display.
- **Naming (PSR-12 + Laravel)**: Controllers `PascalCaseController`; models singular `PascalCase`; migrations snake_case with the standard timestamp prefix; Form Requests `VerbNounRequest` (e.g. `StoreLinkRequest`); resources `NounResource`; database tables plural snake_case; columns snake_case; routes kebab/lowercase. Follow PSR-12; let Pint enforce style.
- **Commit format**: Conventional Commits with a short imperative subject, e.g. `feat: add link creation endpoint`, `fix: prevent code collision on concurrent create`, `test: cover expired redirect`, `docs: document api error envelope`, `chore: configure sail services`.
- **ONE COMMIT PER FEATURE**: Each feature or task is exactly one commit. Never batch multiple features into a single commit. Never split one small feature across several noisy commits. The commit list in `docs/phases.md` is the intended order.
- **Pin exact dependency versions**: Add dependencies with exact versions (no `^`/`~` drift for the lockfile). Commit `composer.lock`. Any dependency change is its own commit and must be approved first (see Boundaries).
- **DB migration rule**: All schema changes go through migration files only. Never edit a migration that has already been applied/committed to shared history — add a new migration instead. Never change schema by hand in the database. Model `$fillable`/`$casts` changes accompany the migration that introduces the columns.

## Error handling & logging

- **Every external/fallible call handles failure**: Database writes for click recording, code generation, and token operations must handle failure explicitly. Click recording failures are caught, logged, and must not break the redirect.
- **Friendly user errors vs detailed logs**: Users receive a short, safe message and a machine-readable code. Full context (exception class, stack, inputs sans secrets) goes to logs only.
- **No stack traces to users**: `APP_DEBUG=false` outside local. Never render framework debug pages or exception traces in API responses.
- **One consistent JSON error format** (see `docs/api-contracts.md`):
  ```json
  { "error": { "code": "validation_failed", "message": "The given data was invalid.", "details": { "target_url": ["The target url must be a valid URL."] } } }
  ```
  `details` is omitted when there is nothing to add.
- **Structured logging from day one**: Log with context arrays, not string interpolation, e.g. `Log::warning('click.record_failed', ['link_id' => $link->id, 'reason' => $e->getMessage()])`. Use consistent dotted event keys (`auth.login_failed`, `link.created`, `redirect.expired`, `click.record_failed`).

## Security

- **No hardcoded secrets**: All secrets/config come from `.env`. `.env` is git-ignored; `.env.example` holds dummy values only.
- **Auth**: Protect all link and stats endpoints with `auth:sanctum`. Tokens are bearer tokens; store only hashes server-side. Logout revokes the current token.
- **Validate all input server-side** via Form Requests. Never trust client input. `target_url` must be a valid `http`/`https` URL; `expires_at` must be a future datetime when present.
- **Queries**: Eloquent / parameter-bound queries only. Never interpolate request input into SQL.
- **Rate limiting**: Apply `throttle` middleware — a stricter limit on auth endpoints, a standard limit on authenticated API endpoints keyed by user, and a limit on the public redirect keyed by IP. Return standard `X-RateLimit-*` headers and `429` on exceed.
- **IP privacy**: Never store raw client IPs. Hash (sha256 with the app key as salt) or truncate before persistence.
- **Protected routes & access** (document who can call what):
  - `POST /api/register`, `POST /api/login` — public, throttled tightly.
  - `POST /api/logout` — authenticated (own token).
  - `POST /api/links`, `GET /api/links` — authenticated; scoped to the caller.
  - `GET /api/links/{id}`, `GET /api/links/{id}/stats`, `DELETE /api/links/{id}` — authenticated AND owner-only (403 for non-owners).
  - `GET /{code}` — public; only leaks the redirect target, never owner or analytics data.

## Simplicity / YAGNI-KISS

- Build only what the current phase requires. No speculative abstraction, no config toggles for features that do not exist, no premature caching.
- Prefer the framework's built-in solution over a custom one. If a feature is not in the PRD, it does not get built without moving it into scope (see Boundaries).

## Code style

- Comments are sparse and explain **why**, not what. Concise docstrings on non-obvious methods only.
- No emoji anywhere in code, comments, commits, or docs.
- No mention of AI, assistants, or authorship attribution anywhere.
- Conventional Commits, imperative subject, one per feature.
- Let Pint own formatting; do not hand-format against it.

## Boundaries

- **No wholesale delete/rewrite** of working code. Make targeted changes; preserve unrelated behavior.
- **Do not change `docs/PRD.md` or `docs/architecture.md`** without explicitly flagging the change and why, and getting sign-off first.
- **No new dependency without approval**. Propose it (what, why, version, size) and wait.
- **Ask when ambiguous** rather than guessing at product behavior.
- **Stop after two failed fix attempts** on the same problem; report what was tried and the current state instead of thrashing.
- **Scope discipline**: classify any new idea as (a) current phase, (b) a new phase, or (c) backlog in `docs/phases.md`. Do not silently expand the current phase.
