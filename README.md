# Vayu

A lightweight, production-capable PHP micro-framework. Vayu gives you modern
routing, a middleware pipeline, an Active-Record ORM, validation, sessions/CSRF,
token APIs with throttling, caching, events and real-time WebSockets — with a
small, readable core and **no mandatory build step**.

- **PHP 8.1+**, PSR-4, `public/` webroot.
- Runs on a bare checkout: a runtime autoloader means `composer install` is
  optional (only needed for third-party extras like PHPMailer / Workerman).
- A single `vayu` CLI for serving, migrations, caching and scaffolding.

## Requirements

- PHP 8.1 or newer (developed on 8.3) with `pdo`, `mbstring`, `json`.
- Optional: Composer (for PHPMailer, phpdotenv, Workerman), `pcntl`/`posix`
  (real-time server), `redis` (Redis cache).

## Quick start

```bash
cp .env.example .env
php vayu setup          # app key, storage dirs, migrations (add --seed for demo data)
php vayu run            # http://127.0.0.1:8000
```

Then open `http://127.0.0.1:8000/` and `http://127.0.0.1:8000/contact`.

## Project structure

```
public/            Webroot + front controller (index.php)
bootstrap/         autoload.php (runtime PSR-4) + app.php (wiring)
config/            Config files returning arrays (app, database, cache, events, websocket, …)
routes/            web.php and api.php route definitions
core/              The framework (namespace Vayu\)
  Http/ Routing/ Middleware/ Database/ Validation/ Session/
  Auth/ Cache/ Events/ Realtime/ Console/ Config/ Logging/ Exceptions/
app/               Your application code (namespace App\)
  Controllers/ Models/ Events/ Listeners/ Realtime/ Middleware/
database/          migrations/ and seeders/
resources/views/   PHP views
storage/           logs, cache, uploads (git-ignored)
tests/             Zero-dependency test runner
```

Namespaces map `Vayu\` → `core/` and `App\` → `app/`.

## CLI

```
setup              First-time setup: env, app key, storage, migrations
run                Run the PHP development server
websocket          Start the Workerman real-time WebSocket server
about              Application information
doctor             Full system health check
route:list         List all registered routes
key:generate       Generate and set APP_KEY in .env
migrate            Run pending migrations
migrate:rollback   Roll back the last batch
migrate:status     Show each migration's status
migrate:fresh      Drop all tables and re-migrate
db:seed            Seed the database
cache:clear        Flush the cache store
config:cache       Compile config into a fast cached map
config:clear       Remove the compiled config cache
optimize           Build production caches (config)
optimize:clear     Clear config + application caches
make:model         Scaffold a model
make:migration     Scaffold a migration
make:seeder        Scaffold a seeder
```

## Routing & middleware

```php
// routes/web.php
$router->get('/hello/{name?}', [WelcomeController::class, 'hello'])->name('hello');

$router->group(['prefix' => 'api', 'middleware' => ['auth', 'throttle:60,1']], function ($router) {
    $router->get('/user', [AuthController::class, 'me']);
});
```

Route params inject by name; type-hint `Request` (or a `FormRequest`) to get it
injected. Middleware runs as a pipeline; register aliases in `bootstrap/app.php`
and use `"name"` or `"name:args"` on routes. Global middleware (session, CSRF)
is applied to every request.

## Data (ORM)

```php
$user = User::create(['name' => 'Ada', 'email' => 'ada@x.io']);
$admins = User::where('role', 'admin')->orderBy('name')->get();
```

Query builder, Active-Record models, schema/migrations and seeders live under
`core/Database/`. See `docs/architecture.md`.

## Forms, APIs, cache, events

- **Validation / CSRF:** `FormRequest` classes, `Validator`, flashed errors and
  old input, `csrf_field()`. See the `/contact` demo.
- **APIs:** token auth (`auth` middleware, Bearer or session), rate limiting
  (`throttle:max,minutes`), JSON helpers. See the `/api/*` demos.
- **Cache:** `cache()->remember(...)` over file/array/redis stores.
- **Events:** `event(new UserRegistered($u))` with listeners in `config/events.php`.

## Real-time (WebSockets)

```bash
composer require workerman/workerman
php vayu websocket           # ws://0.0.0.0:8080
```

Push from any controller: `broadcast('room.1', 'chat.message', ['text' => 'hi'])`.
Events implementing `Vayu\Realtime\BroadcastsEvents` broadcast automatically.
Full guide: [`docs/realtime.md`](docs/realtime.md). Browser demo:
`public/realtime.html`.

## Production

```bash
composer install --no-dev
php vayu optimize            # compile the config cache
```

Point your web server's document root at `public/`. Set `APP_ENV=production`
and `APP_DEBUG=false`. Re-run `optimize` (or `config:clear`) after changing
`.env`, since cached config freezes env values at build time.

## Testing

```bash
php tests/run.php
```

A zero-dependency runner: each `tests/*_test.php` returns a
`callable(Assert): void`.

## Documentation

- [`docs/usage.md`](docs/usage.md) — day-to-day usage.
- [`docs/architecture.md`](docs/architecture.md) — how the pieces fit.
- [`docs/realtime.md`](docs/realtime.md) — WebSockets & broadcasting.

## Security

Never commit secrets. All credentials (SMTP, DB, app key) belong in `.env`
(git-ignored); `.env.example` documents the keys. If a secret is ever committed,
rotate it at the provider — removing it from code does not undo exposure.

## License

MIT.
