# Architecture — laravel-shortlink

## App flow

```
Register / Login (Sanctum)
        │  issues bearer token
        ▼
Create link  ──POST /api/links──▶  validate (FormRequest)
        │                          generate unique code
        │                          persist Link (owner = auth user)
        │                          return LinkResource (201)
        ▼
Public visit ──GET /{code}──▶  resolve Link by code
        │                       ├─ not found            → 404 Blade page
        │                       ├─ soft-deleted         → 410 Blade page
        │                       ├─ expired (expires_at) → expired Blade page
        │                       └─ active               → record Click, then redirect (302)
        ▼
Record click  ──▶  persist Click (referrer, user_agent, hashed ip, created_at)
        │           failure is logged, redirect still proceeds
        ▼
View stats   ──GET /api/links/{id}/stats──▶  authorize owner
                                              aggregate Click rows
                                              return stats envelope (200)
```

## Request lifecycle (API)

1. Route in `routes/api.php` is matched.
2. `throttle` middleware applies rate limits; `auth:sanctum` guards protected routes.
3. A Form Request validates and authorizes input server-side.
4. The controller invokes model/query logic (Eloquent only).
5. An API Resource shapes the response; a shared helper wraps it in the success envelope.
6. Exceptions are converted to the single JSON error envelope by the exception handler.

The public redirect route lives in `routes/web.php` (it returns HTTP redirects and HTML, not JSON).

## Proposed folder / file tree

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php         # register, login, logout
│   │   │   ├── LinkController.php         # index, store, show, destroy
│   │   │   └── StatsController.php        # show (per-link analytics)
│   │   └── RedirectController.php         # public GET /{code}
│   ├── Requests/
│   │   ├── RegisterRequest.php
│   │   ├── LoginRequest.php
│   │   └── StoreLinkRequest.php
│   ├── Resources/
│   │   ├── LinkResource.php
│   │   ├── LinkCollection.php
│   │   └── ClickResource.php
│   └── Middleware/
│       └── (default Laravel middleware; no custom needed for v1)
├── Models/
│   ├── User.php
│   ├── Link.php
│   └── Click.php
├── Support/
│   ├── CodeGenerator.php                  # unique short-code generation
│   ├── ClientIp.php                       # hash/truncate IP for storage
│   └── ApiResponse.php                    # success/error envelope helpers
└── Exceptions/
    └── Handler.php                        # renders the single JSON error format

database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_personal_access_tokens_table.php
│   ├── 2026_01_01_000100_create_links_table.php
│   └── 2026_01_01_000200_create_clicks_table.php
├── factories/
│   ├── UserFactory.php
│   ├── LinkFactory.php
│   └── ClickFactory.php
└── seeders/
    └── DatabaseSeeder.php

resources/views/errors/
├── 404.blade.php                          # unknown code
├── 410.blade.php                          # deleted / gone
└── expired.blade.php                      # expired link

routes/
├── api.php                                # auth + link + stats endpoints
└── web.php                                # public redirect GET /{code}

tests/
├── Feature/
│   ├── AuthTest.php
│   ├── LinkCreateTest.php
│   ├── LinkListShowDeleteTest.php
│   ├── RedirectTest.php
│   └── StatsTest.php
└── Unit/
    ├── CodeGeneratorTest.php
    └── ClientIpTest.php
```

## Tech stack with rationale

- **Laravel 12.x (PHP 8.2+)** — Batteries-included framework: routing, validation via Form Requests, Eloquent, API Resources, and a first-class testing story. Matches the requested stack.
- **Laravel Sanctum** — Lightweight API token auth without the overhead of full OAuth; standard for Laravel token-based APIs.
- **MySQL 8.x** — Reliable relational store; the data is naturally relational (users → links → clicks) and needs unique constraints and indexed lookups by code.
- **Laravel Sail (Docker)** — Reproducible local environment bundling app + MySQL so contributors need only Docker.
- **Pest on PHPUnit** — Concise, readable feature and unit tests; runs the same underlying PHPUnit engine Laravel ships with.
- **Laravel Pint** — Zero-config PSR-12 formatter to keep style consistent.

## Data model

### User
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | |
| password | string (hashed) | |
| created_at / updated_at | timestamps | |

Relationships: `hasMany(Link)`.

### Link
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK → users.id | indexed, cascade on delete |
| code | string(16), unique | indexed; the short code |
| target_url | text | validated http/https URL |
| expires_at | datetime, nullable | null = never expires |
| created_at / updated_at | timestamps | |
| deleted_at | datetime, nullable | soft delete → 410 after removal |

Relationships: `belongsTo(User)`, `hasMany(Click)`.

### Click
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| link_id | bigint FK → links.id | indexed, cascade on delete |
| referrer | string, nullable | from Referer header |
| user_agent | string, nullable | from User-Agent header |
| ip | string, nullable | hashed (sha256) or truncated; never raw |
| created_at | datetime | click timestamp (no updated_at needed) |

Relationships: `belongsTo(Link)`.

Indexes: `links.code` unique; `links.user_id`; `clicks.link_id`; `clicks.created_at` (for time-bucketed stats).

## Where state lives

- **Persistent state** — MySQL is the single source of truth for users, links, and clicks.
- **Auth state** — Bearer tokens are stored in `personal_access_tokens` (Sanctum); the client holds the plaintext token, the server holds a hash.
- **No server session** for the API (stateless token auth). The redirect route is stateless too.
- **Configuration/secrets** — environment variables only (`.env`), never in code.
- **Rate-limit counters** — Laravel's cache store (default database/file per config); not part of the domain data.

## External dependencies and required env vars

External services: none beyond the MySQL database and Docker runtime. No third-party APIs are called at runtime.

Required environment variables (see `.env.example`):

| Variable | Purpose |
|---|---|
| `APP_KEY` | Application encryption key; must be generated. |
| `APP_URL` | Base host used to build full short URLs and links in responses. |
| `APP_DEBUG` | Debug flag; false outside local, must be false in production. |
| `DB_CONNECTION` | Database driver (`mysql`). |
| `DB_HOST` | Database host (`mysql` under Sail). |
| `DB_PORT` | Database port (`3306`). |
| `DB_DATABASE` | Database name. |
| `DB_USERNAME` | Database user. |
| `DB_PASSWORD` | Database password. |

Sanctum requires no extra env vars for token (non-SPA) usage. If SPA cookie auth is ever added it would need `SANCTUM_STATEFUL_DOMAINS` and session config, which is out of scope for v1.
