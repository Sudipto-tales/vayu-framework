# Vayu Feature Reference

## Core

- **Runtime PSR-4 autoloading** — boots without `composer install`
  (`Vayu\` → `core/`, `App\` → `app/`).
- **Application registry** — `bind`/`singleton`/`instance`/`make`; services:
  `config`, `logger`, `router`, `request`, `db`, `session`, `auth`, `cache`,
  `rate_limiter`, `broadcast`, `events`.
- **Config** — dot-notation repository, lazy file loading, compiled cache.
- **Logging** — daily/single channels under `storage/logs`.
- **Exception handling** — HTML or JSON error responses by content negotiation.

## HTTP

- Router with dynamic params, optional params, named routes, method
  constraints, and groups (prefix + middleware).
- Middleware **pipeline**: global + per-route, aliases and parameters
  (`throttle:60,1`), class/instance/closure middleware.
- `Request` (immutable, JSON bodies, method spoofing, `bearerToken()`,
  `wantsJson()`, file uploads) and a fluent `Response`.

## Data (ORM)

- `ConnectionManager` (sqlite/mysql) + `DB` facade.
- `QueryBuilder`: where/orWhere/in/null/raw, joins, order/group/limit/offset,
  insert/insertGetId/update/delete, aggregates, pluck/value/exists.
- Active-Record `Model`: fillable/guarded, dirty tracking, timestamps,
  `find`/`all`/`where`/`create` via `__callStatic`, `JsonSerializable`.
- `Schema`/`Blueprint`/`Grammar` cross-driver DDL, `Migrator`, `Seeder`.

## Web security & forms

- Native/array `Session` with flash messages, old input and an error bag.
- `StartSession` + `VerifyCsrfToken` middleware (CSRF skips GET/HEAD/OPTIONS and
  `api/*`).
- `Validator` with a broad rule set, `ValidationException`, `FormRequest`
  (validated before controller injection), `UploadedFile`.

## APIs & throttling

- Token auth (`AuthManager`, sha256 tokens in `api_tokens`), Bearer or session,
  `Authenticate` middleware.
- `RateLimiter` (cache-backed fixed window) + `ThrottleRequests` with
  `X-RateLimit-*` / `Retry-After` headers.
- JSON helpers and a `/up` health endpoint.

## Cross-cutting

- **Cache**: `Repository` over file/array/redis; `remember`, `increment`, etc.
- **Events**: `Dispatcher` with named listeners (`config/events.php`), wildcard
  observers, `Closure`/`Class@method`/`handle()` listeners, halt on `false`.

## Real-time (optional, Workerman)

- WebSocket server (`php vayu websocket`) with a JSON frame protocol,
  channels/subscriptions, heartbeat and origin checks.
- `Broadcaster` pushes from the HTTP process over a loopback bridge with **no
  Workerman dependency in the web SAPI**.
- Events implementing `BroadcastsEvents` auto-broadcast. See
  [realtime.md](realtime.md).

## Tooling (CLI)

`setup`, `run`, `websocket`, `about`, `doctor`, `route:list`, `key:generate`,
`migrate*`, `db:seed`, `cache:clear`, `config:cache`/`config:clear`,
`optimize`/`optimize:clear`, `make:model`/`make:migration`/`make:seeder`.

## Testing

Zero-dependency runner (`php tests/run.php`): each `tests/*_test.php` returns a
`callable(Assert): void`.

## Helpers

`app()`, `config()`, `request()`, `response()`, `json()`, `view()`, `redirect()`,
`back()`, `route()`, `asset()`, `logger()`, `auth()`, `cache()`, `event()`,
`broadcast()`, `session()`, `csrf_token()`/`csrf_field()`, `old()`,
`errors()`/`error()`, `e()`, `abort()`, `env()`.
