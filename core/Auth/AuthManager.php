<?php

namespace Vayu\Auth;

use Vayu\Application;
use Vayu\Database\DB;

/**
 * Minimal authentication manager supporting API tokens (Sanctum-style) and
 * session-based web login. Users are plain associative arrays from the `users`
 * table.
 */
class AuthManager
{
    protected string $tokensTable = 'api_tokens';

    protected string $usersTable = 'users';

    /** @var array<string, mixed>|null */
    protected ?array $user = null;

    protected bool $resolved = false;

    public function __construct(protected Application $app)
    {
    }

    /* ---- Current user ---- */

    public function setUser(?array $user): void
    {
        $this->user = $user;
        $this->resolved = true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        // Try the session (web guard).
        $id = $this->session()?->get('__auth_id');
        if ($id !== null) {
            $this->user = DB::table($this->usersTable)->find($id);
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): mixed
    {
        return $this->user()['id'] ?? null;
    }

    /* ---- Web (session) login ---- */

    public function login(array $user): void
    {
        $this->setUser($user);
        $this->session()?->put('__auth_id', $user['id'] ?? null);
    }

    public function loginUsingId(mixed $id): ?array
    {
        $user = DB::table($this->usersTable)->find($id);
        if ($user !== null) {
            $this->login($user);
        }

        return $user;
    }

    public function logout(): void
    {
        $this->setUser(null);
        $this->session()?->forget('__auth_id');
    }

    /**
     * Verify credentials against the users table (does not log in).
     *
     * @return array<string, mixed>|null
     */
    public function validateCredentials(string $email, string $password): ?array
    {
        $user = DB::table($this->usersTable)->where('email', $email)->first();

        if ($user === null || !password_verify($password, (string) ($user['password'] ?? ''))) {
            return null;
        }

        return $user;
    }

    /* ---- API tokens ---- */

    /**
     * Create a personal access token and return the plaintext value (shown
     * once). Only the SHA-256 hash is stored.
     */
    public function createToken(int $userId, string $name = 'api'): string
    {
        $plain = bin2hex(random_bytes(32));

        DB::table($this->tokensTable)->insert([
            'user_id' => $userId,
            'name' => $name,
            'token' => hash('sha256', $plain),
            'last_used_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $plain;
    }

    /**
     * Resolve the user owning a plaintext bearer token.
     *
     * @return array<string, mixed>|null
     */
    public function userFromToken(string $plain): ?array
    {
        $row = DB::table($this->tokensTable)
            ->where('token', hash('sha256', $plain))
            ->first();

        if ($row === null) {
            return null;
        }

        DB::table($this->tokensTable)
            ->where('id', $row['id'])
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        return DB::table($this->usersTable)->find($row['user_id']);
    }

    protected function session(): ?\Vayu\Session\Session
    {
        try {
            return $this->app->make('session');
        } catch (\Throwable) {
            return null;
        }
    }
}
