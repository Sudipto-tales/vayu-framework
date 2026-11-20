# Vayu Usage

## Setup

```bash
cp .env.example .env
php vayu setup            # app key, storage dirs, migrations (--seed for demo data, --fresh to reset)
php vayu run              # dev server on http://127.0.0.1:8000
```

`composer install` is optional — it's only needed for PHPMailer, phpdotenv and
Workerman. The framework itself runs on a bare checkout.

## Routing

Define routes in `routes/web.php` and `routes/api.php` (both receive `$router`):

```php
$router->get('/', [WelcomeController::class, 'index'])->name('home');
$router->get('/hello/{name?}', [WelcomeController::class, 'hello']);
$router->post('/contact', [ContactController::class, 'submit']);

$router->group(['prefix' => 'api', 'middleware' => ['auth', 'throttle:60,1']], function ($router) {
    $router->get('/user', [AuthController::class, 'me']);
});
```

- Params: `{id}` required, `{id?}` optional. They inject into the handler by name.
- Type-hint `Vayu\Http\Request` (or a `FormRequest`) and it is injected.
- `php vayu route:list` prints the table.

## Controllers

```php
namespace App\Controllers;

use Vayu\Http\Request;
use Vayu\Http\Response;

class WelcomeController
{
    public function hello(Request $request, ?string $name = null): Response
    {
        return view('welcome', ['name' => $name ?? 'world']);
    }
}
```

Return a `Response`, a string (HTML), or an array/object (JSON).

## Views

PHP views live in `resources/views/`. Render with `view('name', $data)` and
escape output with `e()`:

```php
<h1>Hello, <?= e($name) ?></h1>
<?= csrf_field() ?>
```

## Forms, validation & CSRF

A `FormRequest` validates before the controller runs; failures redirect back
with flashed errors and old input (or return 422 JSON for API clients):

```php
class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email', 'message' => 'required|min:10'];
    }
}
```

In views: `old('email')`, `error('email')`, `errors()`.

## Database

```php
use App\Models\User;

$user  = User::create(['name' => 'Ada', 'email' => 'ada@x.io']);
$found = User::find(1);
$admins = User::where('role', 'admin')->orderBy('name')->get();

// Query builder / facade
DB::table('users')->where('active', 1)->count();
```

Migrations and seeders:

```bash
php vayu make:migration create_posts_table
php vayu migrate            # rollback | status | fresh
php vayu make:seeder PostSeeder
php vayu db:seed
```

## APIs, auth & throttling

```php
// Issue a token
$token = auth()->attempt($email, $password);     // -> store/return it

// Protect routes
$router->get('/api/user', [AuthController::class, 'me'])
    ->middleware(['auth', 'throttle:60,1']);       // 60 requests / minute
```

Clients send `Authorization: Bearer <token>`. Rate-limited responses carry
`X-RateLimit-*` / `Retry-After`. JSON helpers: `json($data, $status)`.

## Cache & events

```php
$value = cache()->remember('key', 300, fn () => expensive());
event(new UserRegistered($user));                  // listeners in config/events.php
```

## Real-time

```bash
composer require workerman/workerman
php vayu websocket
```

Broadcast from anywhere: `broadcast('room.1', 'chat.message', ['text' => 'hi'])`.
See [realtime.md](realtime.md).

## Environment keys

See `.env.example`. Common: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`,
`APP_KEY`, `DB_*`, `CACHE_DRIVER`, `MAIL_*`, `WS_*`. Never commit real secrets —
`.env` is git-ignored.

## Production

```bash
composer install --no-dev
php vayu optimize          # compile config cache
```

Serve with the document root at `public/`. Re-run `optimize` (or `config:clear`)
after editing `.env`, since cached config freezes env values.
