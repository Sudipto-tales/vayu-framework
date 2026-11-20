# Vayu Architecture

Vayu is a small service registry plus an HTTP/console lifecycle. There is no
full DI container by design — just enough wiring to avoid the old
`global $pdo` / loose-function style.

## Boot sequence

1. **`public/index.php`** (the only webroot entry) requires `bootstrap/app.php`.
2. **`bootstrap/autoload.php`** registers a runtime PSR-4 autoloader
   (`Vayu\` → `core/`, `App\` → `app/`), loads Composer's autoloader if present,
   and loads `core/Support/helpers.php`. The app boots on a bare checkout.
3. **`bootstrap/app.php`** loads `.env`, creates the `Application`, registers
   exception handling, global middleware (session + CSRF) and middleware
   aliases, then requires `routes/web.php` and `routes/api.php`.
4. `Application::run()` captures the `Request`, dispatches it through the
   `Router`, and sends the `Response`.

The console entry (`bin/vayu`) requires the same `bootstrap/app.php` and calls
`Application::runConsole()`.

## The Application (`core/Application.php`)

A service registry with `bind` / `singleton` / `instance` / `make`. Registered
services include: `config`, `logger`, `router`, `request`, `db`, `session`,
`auth`, `cache`, `rate_limiter`, `broadcast` and `events`. Config is loaded
lazily and can be served from a compiled cache (see "Optimization").

## HTTP layer (`core/Http`, `core/Routing`, `core/Middleware`)

- **`Request`** — an immutable snapshot (method, path, query, body, headers,
  cookies, files). Decodes JSON bodies and supports method spoofing.
- **`Response`** — content/status/headers value object with `json()`, `html()`,
  `redirect()`, `created()`, `noContent()`, etc.
- **`Router`** — dynamic params (`{id}`, `{name?}`), named routes, method
  constraints, groups (prefix + middleware), and a middleware **pipeline**.
  Handlers may be `[Controller::class, 'method']`, `"Controller@method"`, or a
  closure. The `Request` (or a `FormRequest` subclass) is injected by type-hint;
  route params inject by name.
- **`Middleware\Pipeline`** — runs global + route middleware around the handler.
  Supports class names, instances, closures, aliases and parameters
  (`throttle:60,1`). Built-ins: `StartSession`, `VerifyCsrfToken`,
  `ThrottleRequests`, `Authenticate`.

## Data layer (`core/Database`)

`ConnectionManager` (service `db`, sqlite/mysql) → `DB` facade → `QueryBuilder`
→ Active-Record `Model` (fillable/guarded, dirty tracking, timestamps,
`__callStatic` finders) → `Schema`/`Blueprint`/`Grammar` (cross-driver DDL) →
`Migrator` + `Seeder`. Each ships a CLI command.

## Supporting services

- **Session/CSRF** (`core/Session`, middleware) — flash messages, old input, an
  error bag and a CSRF token; CSRF is verified for non-idempotent, non-API
  requests.
- **Validation** (`core/Validation`) — `Validator`, `ValidationException`,
  `FormRequest` (validated before injection).
- **Auth & throttling** (`core/Auth`, `core/RateLimiting`) — token minting +
  verification (`api_tokens`), session login, Bearer/session `Authenticate`,
  file/cache-backed fixed-window `RateLimiter`.
- **Cache** (`core/Cache`) — `Repository` over file/array/redis stores.
- **Events** (`core/Events`) — `Dispatcher` with named and wildcard listeners;
  listeners from `config/events.php`.
- **Real-time** (`core/Realtime`) — a Workerman WebSocket server plus a
  loopback broadcast bridge. See [realtime.md](realtime.md).

## Optimization

`php vayu optimize` compiles every side-effect-free, array-returning config file
into `storage/cache/config.php`. On boot, `Application` preloads that map into
the `Config` repository, skipping per-file `require`s. Legacy side-effecting
config files are detected via the PHP tokenizer and never cached. Route caching
is intentionally omitted because routes use closures.

## Legacy bridge (strangler-fig)

The old "Coder Framework" files remain on disk. The new `Vayu\`/`App\` stack
runs alongside them; the old array-route map is bridged only when
`VAYU_LEGACY_ROUTES=true` (`Application::registerLegacyRoutes()`), and new routes
always win.
