# Design decisions

The engineering choices behind laravel-shortlink, the reasons for them, and the bugs and edge cases
found along the way. Each one is reflected in the code referenced in its section.

## 1. Redirects use 302, not 301

`RedirectController` answers a live code with `redirect()->away($target, 302)`. A 301 is cached by
browsers and proxies, so later changes in how a code resolves (deletion, expiry) would never be seen
by a client that had already visited it, and repeat visits would stop producing click rows. A
temporary redirect keeps every visit flowing through the app.

## 2. Deleted and expired links answer 410 with their own pages

Links are soft-deleted. The redirect looks codes up with `withTrashed()`, so a removed code returns
410 Gone with `errors/410.blade.php` instead of a 404 that would suggest the code never existed. An
expired link also returns 410, with a separate `errors/expired.blade.php` page so a visitor can tell
the two cases apart, and an unknown code returns 404. The three pages share one plain, accessible
template and expose nothing about the owner. Expiry is checked on every visit
(`Link::isExpired()`), so there is no background job to keep in sync.

## 3. Codes are random, and never reissued

`CodeGenerator` produces 8-character alphanumeric codes with `Str::random` and checks each
candidate against all links, soft-deleted ones included, retrying up to five times before failing
loudly. Including deleted links means a code that once pointed somewhere cannot later be handed to
a different owner and silently redirect old shared links to a new target. The `links.code` column
carries a unique index as the final guard; with 62^8 possible codes a race between the check and
the insert is negligible, and if it ever happens the index rejects the duplicate rather than storing
it. Codes are generated only; custom vanity codes are out of scope.

## 4. Raw client IPs are never stored

Each click stores `hash_hmac('sha256', $ip, APP_KEY)` (`App\Support\ClientIp`) in place of the IP.
Keying the hash with the app key means a stored value cannot be reversed by hashing the IPv4 address
space without the key, while the same visitor still hashes to the same value, which is what the
`unique_ips` total counts. The stats `recent` list is shaped by `ClickResource`, which exposes
referrer, user agent and time and never the IP hash.

## 5. Click recording can never break a redirect

Recording a click is wrapped in a `try/catch`: a failed insert is logged as `click.record_failed`
and the visitor still gets their 302. The analytics row is worth less than the redirect the visitor
asked for.

Edge case: `referrer` and `user_agent` are 255-character columns, so an oversized header would have
failed the insert and taken the logged failure path on every visit from that client. Both headers
are now truncated to the column width before the insert, so a long header is stored, cut to fit, and
the click still counts.

## 6. One JSON error envelope, and the 403 bug it exposed

Every API response uses the same envelope: `data` and optional `meta` on success,
`error.{code, message, details}` on failure. `App\Exceptions\Handler`, registered in
`bootstrap/app.php`, maps validation, authentication, authorization, not-found, throttling and
unexpected exceptions to stable codes, and never renders a stack trace.

Bug found in manual testing: controllers originally called `abort(403)`, and Laravel converts
authorization failures to `AccessDeniedHttpException` before a custom renderer runs, so non-owner
requests came back as `server_error` instead of `forbidden`. Controllers now throw
`AuthorizationException`, and the handler matches `AccessDeniedHttpException` explicitly as well.

## 7. Ownership is enforced in the controller, with 403 versus 404

Link and stats endpoints require `auth:sanctum`. Show, delete and stats load the link by id and
compare `user_id` with the caller, throwing `AuthorizationException` on a mismatch. That gives the
documented split: 404 when the id does not exist, 403 when it exists but belongs to someone else.
The list endpoint queries through `$request->user()->links()`, so it can only ever return the
caller's rows, and `per_page` is capped at 100.

## 8. Three named rate limiters, and JSON 429s on the redirect route

`AppServiceProvider` defines three limiters: `auth` at 10 per minute by IP for register and login,
`api` at 60 per minute by user id for authenticated endpoints, and `redirect` at 120 per minute by
IP for the public redirect. Laravel's throttle middleware supplies the `X-RateLimit-*` and
`Retry-After` headers.

Two edge cases came up. The headers live on the `TooManyRequestsHttpException`, not on the response
the handler builds, so the handler copies them across. And the redirect route is neither `api/*`
nor a JSON request, so its 429 originally fell through to Laravel's HTML error page. The handler now
always renders a 429 as the JSON envelope, while the redirect route's 404, 410 and expired cases
still render their Blade pages.

## 9. Daily stats are bucketed in PHP

`StatsController` groups click timestamps by calendar date in PHP rather than with `DATE()` or
`strftime()` in SQL. Those functions differ between MySQL and SQLite, and the app runs on both:
MySQL is the deployment target, and the test suite runs on in-memory SQLite. `clicks.created_at` is
indexed so the per-link scan stays fast. The `recent` list is capped at 20 rows.

## 10. Input is validated in Form Requests

`StoreLinkRequest` accepts only `http` and `https` target URLs up to 2048 characters and an
optional `expires_at` that must be in the future, so a link cannot be created already expired.
`target_url` is stored as `text`, so long URLs are never truncated. Link targets cannot be edited
after creation; a different target means a new link.

Bug found in review: an `expires_at` sent with a UTC offset, such as `10:00:00+05:00`, was stored as
`10:00:00` and read back as UTC, so the link expired five hours late. Eloquent's datetime cast
formats a parsed value in its own offset without converting it. The controller now converts the
value to UTC before saving, so any ISO-8601 offset expires at the instant it names.

## 11. Sanctum tokens, revoked on logout

Authentication is Sanctum personal access tokens: register and login return a bearer token, and
logout deletes the current token so it cannot be reused. Failed logins are logged as
`auth.login_failed` and count against the `auth` limiter.

Testing edge case: within one test process Sanctum's guard caches the resolved user, so a second
request with a just-revoked token would still authenticate. `AuthTest` calls `Auth::forgetGuards()`
between the two requests. Real requests are separate processes and always re-resolve the token, so
this is a test-only concern.

## 12. MySQL through Sail, SQLite as the fallback

The documented runtime is Laravel Sail with MySQL (`compose.yaml`). Where Docker is unavailable the
app runs unchanged on SQLite, because nothing in the code depends on driver-specific SQL, and the
test suite always uses in-memory SQLite via `phpunit.xml`. CI runs Pint and the Pest suite on PHP
8.2, 8.3 and 8.4.
