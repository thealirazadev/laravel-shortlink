# Product Requirements — laravel-shortlink

## What we're building

A URL shortener delivered as a REST API. Authenticated users create short links that map a short, unique code to a target URL. Anyone visiting the short code is redirected to the target URL, and the visit is recorded as a click. Owners can view click analytics for each of their links. The service also serves a small set of accessible HTML pages for redirect edge states (unknown code, deleted link, expired link).

## Target user

Developers and small teams who need programmatic short links with basic click analytics, without a heavy third-party service. They authenticate with an API token, integrate link creation into their own tools, and read analytics back through JSON.

## Core features (prioritized)

1. **Authentication (Sanctum tokens)** — Register, log in to obtain a bearer token, log out to revoke it. All link-management and analytics endpoints require a valid token. Highest priority: nothing else is usable without it.

2. **Create short link** — `POST /api/links` with a `target_url` and optional `expires_at`. The server generates a unique `code`, stores the link against the authenticated owner, and returns the full short URL.

3. **Public redirect** — `GET /{code}` looks up an active, non-expired link and issues an HTTP redirect to its `target_url`. Unknown codes return 404, deleted links 410, expired links a dedicated expired page.

4. **Record click** — On each successful redirect, persist a click row with referrer, user agent, and a hashed/truncated IP, plus timestamp. Must not block or fail the redirect if recording has a non-fatal issue (log and continue).

5. **View analytics** — `GET /api/links/{id}/stats` returns per-link aggregates: total clicks, clicks over time, and recent click metadata. Owner-scoped only.

6. **Manage links** — `GET /api/links` (list owned), `GET /api/links/{id}` (show one owned), `DELETE /api/links/{id}` (soft-remove so the code returns 410 afterward).

## Non-goals

- Custom vanity codes chosen by the user (auto-generated codes only in v1).
- Public sign-up UI, dashboards, or any SPA/front-end beyond the minimal error/redirect Blade pages.
- Editing a link's `target_url` after creation.
- Team/organization sharing, roles, or multi-owner links.
- Bot filtering, geolocation, device parsing, or advanced analytics beyond counts and raw click metadata.
- QR codes, link previews, UTM building, or password-protected links.
- Real-time analytics streaming or webhooks.

## Success criteria per core feature

- **Authentication** — A registered user can obtain a token and call a protected endpoint successfully; an invalid/absent token yields a 401 in the standard error envelope; logout revokes the token so reuse yields 401.
- **Create short link** — A valid request returns 201 with a unique `code` and a full short URL under `APP_URL`; an invalid or non-HTTP(S) `target_url` returns 422 with field errors; codes never collide across concurrent creations.
- **Public redirect** — Visiting a valid code returns a redirect (301/302) to the exact stored `target_url`; unknown code returns 404 HTML; expired link returns the expired HTML page; deleted link returns 410 HTML.
- **Record click** — Each successful redirect creates exactly one click row with referrer, user agent, hashed IP, and timestamp; a recording hiccup is logged but the user still gets their redirect.
- **View analytics** — Owner receives correct total and time-bucketed counts matching the recorded clicks; a non-owner or unauthenticated caller receives 403/401 respectively; a missing link returns 404.
- **Manage links** — List and show return only the caller's links, paginated; delete returns 204 and subsequent visits to the code return 410.
