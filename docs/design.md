# Design — laravel-shortlink

This is an API-first product. The primary design surface is the JSON contract: a single success envelope and a single error envelope used by every API endpoint. The only HTML is a small set of accessible pages for redirect edge states.

## Design principles

- **Predictable envelopes**: Every API response is either the success envelope or the error envelope. Clients can branch on the top-level `data` vs `error` key.
- **Consistent codes**: Machine-readable `error.code` strings are stable and documented; human `message` strings can change without breaking clients.
- **Least surprise**: HTTP status codes match semantics (201 create, 204 delete, 401 unauthenticated, 403 forbidden, 404 not found, 410 gone, 422 validation, 429 throttled).
- **Privacy by default**: No raw IPs, no leaking owner identity on public routes.

## Success envelope

Single resource:

```json
{
  "data": {
    "id": 42,
    "code": "a1B2c3",
    "short_url": "http://localhost/a1B2c3",
    "target_url": "https://example.com/very/long/path",
    "expires_at": null,
    "created_at": "2026-07-15T10:00:00Z"
  }
}
```

Collections add a `meta` block for pagination:

```json
{
  "data": [ /* array of resources */ ],
  "meta": { "current_page": 1, "per_page": 15, "total": 37, "last_page": 3 }
}
```

Rules:
- Timestamps are ISO-8601 UTC (`Z` suffix).
- `short_url` is always built from `APP_URL` + `/` + `code`.
- Null optional fields are present with a `null` value (not omitted), except computed sub-objects.

## Error envelope

Every error uses exactly this shape:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": { "target_url": ["The target url must be a valid URL."] }
  }
}
```

- `code` — stable snake_case string (see `docs/api-contracts.md` for the full list).
- `message` — short, safe, human-readable; never contains stack traces or secrets.
- `details` — optional; present for validation (`field -> [messages]`) and omitted otherwise.

## HTML pages (redirect edge states)

Three minimal, semantic, framework-free pages live under `resources/views/errors/`. They are the only HTML in the product. They must be self-contained (inline or a single small stylesheet), fast, and accessible.

### Shared requirements (accessibility)

- Valid HTML5 with `<!doctype html>` and `<html lang="en">`.
- A single `<main>` landmark containing an `<h1>` that names the state.
- Sufficient color contrast (WCAG AA, at least 4.5:1 for body text).
- Responsive and readable on mobile (viewport meta tag; fluid width; base font >= 16px).
- No JavaScript required to understand or use the page.
- Correct HTTP status set by the controller/response, independent of the page content.
- A clear, plain-language explanation and, where useful, a link back to a safe destination (e.g. the site root) with descriptive link text.
- Focus order is natural (single column, logical DOM order); no keyboard traps.

### 404 — unknown code (`404.blade.php`)

- Status: `404`.
- `<h1>`: "Link not found".
- Body: explains the short link does not exist or was mistyped.

### 410 — deleted / gone (`410.blade.php`)

- Status: `410`.
- `<h1>`: "Link removed".
- Body: explains the link existed but was deleted by its owner and is no longer available.

### Expired (`expired.blade.php`)

- Status: `410` (gone; the resource existed but is no longer served) — documented consistently with `api-contracts.md`.
- `<h1>`: "Link expired".
- Body: explains the link passed its expiry date and no longer redirects.

## Redirect behavior

- Active link → HTTP `302` redirect to `target_url` with `Location` header. (302 chosen so targets can change semantics later without cache poisoning; documented so it is a deliberate choice.)
- The redirect response records the click first, then redirects; click-recording failure is logged and does not change the redirect outcome.
