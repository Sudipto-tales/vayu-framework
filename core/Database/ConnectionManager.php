<?php

namespace Vayu\Database;

use Vayu\Application;

/**
 * Resolves and caches database connections from config/database.php.
 */
class ConnectionManager
{
    /** @var array<string, Connection> */
    protected array $connections = [];

    public function __construct(protected Application $app)
    {
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= (string) $this->app->config('database.default', 'sqlite');

        return $this->connections[$name] ??= $this->makeConnection($name);
    }

    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];
        } else {
            unset($this->connections[$name]);
        }
    }

    protected function makeConnection(string $name): Connection
    {
        $config = $this->app->config("database.connections.{$name}");

        if (!is_array($config)) {
            throw new \RuntimeException("Database connection [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? $name;

        $pdo = match ($driver) {
            'sqlite' => $this->createSqlite($config),
            'mysql' => $this->createMysql($config),
            default => throw new \RuntimeException("Unsupported database driver [{$driver}]."),
        };

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        return new Connection($pdo, $driver);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createSqlite(array $config): \PDO
    {
        $database = (string) ($config['database'] ?? ':memory:');

        if ($database !== ':memory:') {
            if (!$this->isAbsolute($database)) {
                $database = $this->app->basePath($database);
            }

            $dir = dirname($database);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (!is_file($database)) {
                @touch($database);
            }
        }

        $pdo = new \PDO('sqlite:' . $database);

        if (($config['foreign_keys'] ?? true) === true) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createMysql(array $config): \PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        return new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '');
    }

    protected function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
