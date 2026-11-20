<?php

namespace Vayu\Console;

use Vayu\Application;

/**
 * Base class for `php vayu` commands.
 */
abstract class Command
{
    protected static string $name = '';

    protected static string $description = '';

    public function __construct(protected Application $app)
    {
    }

    public static function name(): string
    {
        return static::$name;
    }

    public static function description(): string
    {
        return static::$description;
    }

    /**
     * @param list<string> $args
     */
    abstract public function handle(array $args): int;

    /* ---- Output helpers ---- */

    protected function line(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    protected function info(string $message): void
    {
        $this->line("\033[32m{$message}\033[0m");
    }

    protected function comment(string $message): void
    {
        $this->line("\033[33m{$message}\033[0m");
    }

    protected function error(string $message): void
    {
        fwrite(STDERR, "\033[31m{$message}\033[0m" . PHP_EOL);
    }

    protected function dim(string $message): void
    {
        $this->line("\033[2m{$message}\033[0m");
    }

    /**
     * @param list<string>        $headers
     * @param list<list<string>>  $rows
     */
    protected function table(array $headers, array $rows): void
    {
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        $render = static function (array $cells) use ($widths): string {
            $out = [];
            foreach ($cells as $i => $cell) {
                $out[] = str_pad((string) $cell, $widths[$i]);
            }

            return '  ' . implode('   ', $out);
        };

        $this->comment($render($headers));
        foreach ($rows as $row) {
            $this->line($render($row));
        }
    }
}
