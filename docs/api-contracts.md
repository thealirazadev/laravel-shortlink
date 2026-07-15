# API Contracts — laravel-shortlink

Base URL: value of `APP_URL` (e.g. `http://localhost`). All API endpoints are prefixed with `/api`. The public redirect is at the root (`/{code}`).

## Authentication

- Scheme: **Sanctum bearer tokens**.
- Obtain a token via `POST /api/login` (or `POST /api/register`).
- Send it on protected requests: `Authorization: Bearer <token>`.
- Content type for request/response bodies: `application/json`. Clients should send `Accept: application/json`.

## Conventions

- Success responses use the success envelope (`data`, plus `meta` for collections).
- Error responses use the single error envelope.
- Timestamps are ISO-8601 UTC.
- Owner-scoped endpoints return `403` when the authenticated user is not the link owner, and `404` when the link id does not exist.

## Error envelope (used by all API errors)

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": { "target_url": ["The target url must be a valid URL."] }
  }
}
```

### Stable error codes

| HTTP | `error.code` | When |
|---|---|---|
| 401 | `unauthenticated` | Missing/invalid/revoked token on a protected route. |
| 403 | `forbidden` | Authenticated but not the resource owner. |
| 404 | `not_found` | Resource id does not exist. |
| 422 | `validation_failed` | Input failed Form Request validation (`details` present). |
| 429 | `rate_limited` | Throttle limit exceeded. |
| 500 | `server_error` | Unexpected error (details logged, not returned). |

---

## Auth endpoints

### POST /api/register  (public, throttled)

Request:
```json
{ "name": "Ada", "email": "ada@example.com", "password": "secret123", "password_confirmation": "secret123" }
```
Response `201`:
```json
{ "data": { "user": { "id": 1, "name": "Ada", "email": "ada@example.com" }, "token": "1|plaintexttokenvalue" } }
```
Errors: `422 validation_failed` (e.g. email taken, password too short).

### POST /api/login  (public, throttled)

Request:
```json
{ "email": "ada@example.com", "password": "secret123" }
```
Response `200`:
```json
{ "data": { "user": { "id": 1, "name": "Ada", "email": "ada@example.com" }, "token": "2|plaintexttokenvalue" } }
```
Errors: `422 validation_failed` (bad credentials — do not reveal which field), `429 rate_limited`.

### POST /api/logout  (auth:sanctum)

No body. Revokes the current access token. Response `204` (no content). Errors: `401 unauthenticated`.

---

## Link endpoints (all require auth:sanctum)

### POST /api/links  (create — owner = caller)

Request:
```json
{ "target_url": "https://example.com/very/long/path", "expires_at": "2026-12-31T23:59:59Z" }
```
- `target_url` — required, valid `http`/`https` URL, max length enforced.
- `expires_at` — optional, ISO-8601 datetime, must be in the future.

Response `201`:
```json
{
  "data": {
    "id": 42,
    "code": "a1B2c3",
    "short_url": "http://localhost/a1B2c3",
    "target_url": "https://example.com/very/long/path",
    "expires_at": "2026-12-31T23:59:59Z",
    "created_at": "2026-07-15T10:00:00Z"
  }
}
```
Errors: `401 unauthenticated`, `422 validation_failed`, `429 rate_limited`.

### GET /api/links  (list — caller's links, paginated)

Query params: `page` (default 1), `per_page` (default 15, max 100).

Response `200`:
```json
{
  "data": [
    { "id": 42, "code": "a1B2c3", "short_url": "http://localhost/a1B2c3", "target_url": "https://example.com/...", "expires_at": null, "created_at": "2026-07-15T10:00:00Z" }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```
Empty state returns `data: []` with `total: 0`. Errors: `401 unauthenticated`.

### GET /api/links/{id}  (show — owner only)

Response `200`: the success envelope with a single link (same shape as create response).
Errors: `401 unauthenticated`, `403 forbidden` (not owner), `404 not_found`.

### GET /api/links/{id}/stats  (analytics — owner only)

Response `200`:
```json
{
  "data": {
    "link": { "id": 42, "code": "a1B2c3", "short_url": "http://localhost/a1B2c3", "target_url": "https://example.com/..." },
    "totals": { "clicks": 128, "unique_ips": 57 },
    "clicks_by_day": [
      { "date": "2026-07-13", "clicks": 40 },
      { "date": "2026-07-14", "clicks": 51 },
      { "date": "2026-07-15", "clicks": 37 }
    ],
    "recent": [
      { "referrer": "https://news.example", "user_agent": "Mozilla/5.0 ...", "created_at": "2026-07-15T09:59:00Z" }
    ]
  }
}
```
- `unique_ips` counts distinct hashed IPs (approximate uniqueness; never raw IPs).
- `recent` is capped (e.g. latest 20) and never exposes raw IPs.
Errors: `401 unauthenticated`, `403 forbidden`, `404 not_found`.

### DELETE /api/links/{id}  (delete — owner only)

Soft-deletes the link. Response `204` (no content). The code then returns `410` on public visit.
Errors: `401 unauthenticated`, `403 forbidden`, `404 not_found`.

---

## Public redirect

### GET /{code}  (public)

- Active, non-expired link → `302` redirect with `Location: <target_url>`. Records one click (referrer, user agent, hashed IP, timestamp).
- Unknown code → `404` with the accessible HTML "Link not found" page.
- Soft-deleted link → `410` with the "Link removed" page.
- Expired link (`expires_at` in the past) → `410` with the "Link expired" page.

This route returns HTML/redirects, not JSON. It never exposes owner identity or analytics.

---

## Rate limiting

- **Auth endpoints** (`register`, `login`): stricter limit keyed by IP (for example 10 requests/minute). Repeated failed logins count against it.
- **Authenticated API endpoints**: standard limit keyed by user id (for example 60 requests/minute).
- **Public redirect**: limit keyed by IP (for example 120 requests/minute) to blunt abuse while allowing normal traffic.

On every throttled route the response includes:

| Header | Meaning |
|---|---|
| `X-RateLimit-Limit` | Max requests allowed in the window. |
| `X-RateLimit-Remaining` | Requests remaining in the current window. |
| `Retry-After` | Seconds until the limit resets (present on `429`). |
| `X-RateLimit-Reset` | Unix timestamp when the window resets (present on `429`). |

Exceeding the limit returns `429` with the error envelope and `error.code = "rate_limited"`. Exact numeric limits are configuration and are finalized in Phase 3; the shape of the behavior and headers is fixed here.
