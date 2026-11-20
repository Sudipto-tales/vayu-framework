<?php

use Vayu\Application;
use Vayu\Auth\AuthManager;
use Vayu\Exceptions\HttpException;
use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Middleware\Authenticate;
use Vayu\Database\Schema\Blueprint;
use Vayu\Database\Schema\Schema;

return function (Assert $assert): void {
    $app = new Application(dirname(__DIR__));
    $app->config()->set('database.default', 'sqlite');
    $app->config()->set('database.connections.sqlite.database', ':memory:');

    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->string('role')->default('user');
    });
    Schema::create('api_tokens', function (Blueprint $t): void {
        $t->id();
        $t->bigInteger('user_id');
        $t->string('name')->default('api');
        $t->string('token')->unique();
        $t->timestamp('last_used_at')->nullable();
        $t->timestamp('created_at')->nullable();
    });

    /** @var AuthManager $auth */
    $auth = $app->make('auth');

    // Seed a user with a known password.
    \Vayu\Database\DB::table('users')->insert([
        'name' => 'Ada', 'email' => 'ada@x.io',
        'password' => password_hash('secret123', PASSWORD_BCRYPT), 'role' => 'admin',
    ]);
    $userId = (int) \Vayu\Database\DB::table('users')->where('email', 'ada@x.io')->value('id');

    // Credential check
    $assert->that('valid credentials accepted', $auth->validateCredentials('ada@x.io', 'secret123') !== null);
    $assert->that('wrong password rejected', $auth->validateCredentials('ada@x.io', 'nope') === null);

    // Token mint + resolve
    $plain = $auth->createToken($userId, 'api');
    $assert->that('token is non-empty plaintext', strlen($plain) >= 32);
    $assert->that('token not stored in plaintext', !\Vayu\Database\DB::table('api_tokens')->where('token', $plain)->exists());
    $resolved = $auth->userFromToken($plain);
    $assert->equals('token resolves to user', 'ada@x.io', $resolved['email'] ?? null);
    $assert->that('bogus token resolves to null', $auth->userFromToken('deadbeef') === null);

    // Authenticate middleware: valid bearer passes and sets attribute
    $mw = new Authenticate();
    $next = fn (Request $r) => Response::make($r->attribute('user')['email'] ?? 'none');

    $withToken = new Request('GET', '/api/user', [], [], ['Authorization' => 'Bearer ' . $plain], [], [], null);
    $auth->setUser(null); // reset resolution
    $res = $mw->handle($withToken, $next);
    $assert->equals('authenticated request sees user', 'ada@x.io', $res->getContent());

    // No token -> 401
    $app->make('auth')->setUser(null);
    $status = 0;
    try {
        $noToken = new Request('GET', '/api/user', [], [], ['Accept' => 'application/json'], [], [], null);
        $mw->handle($noToken, $next);
    } catch (HttpException $e) {
        $status = $e->getStatusCode();
    }
    $assert->equals('missing token -> 401', 401, $status);
};
