<?php

/**
 * Zero-dependency test runner for Vayu core.
 *
 *   php tests/run.php
 *   php vayu test        (if wired)
 *
 * Add a *_test.php file in tests/ that returns a callable(Assert): void.
 */

$basePath = require __DIR__ . '/../bootstrap/autoload.php';

final class Assert
{
    public int $passed = 0;
    public int $failed = 0;
    /** @var list<string> */
    public array $failures = [];

    public function that(string $label, bool $condition): void
    {
        if ($condition) {
            $this->passed++;
            fwrite(STDOUT, "  \033[32m✔\033[0m {$label}\n");
        } else {
            $this->failed++;
            $this->failures[] = $label;
            fwrite(STDOUT, "  \033[31m✘ {$label}\033[0m\n");
        }
    }

    public function equals(string $label, mixed $expected, mixed $actual): void
    {
        $this->that(
            $label . "  (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")",
            $expected === $actual
        );
    }
}

$assert = new Assert();

$files = glob(__DIR__ . '/*_test.php') ?: [];
foreach ($files as $file) {
    fwrite(STDOUT, "\n\033[33m" . basename($file) . "\033[0m\n");
    $test = require $file;
    if (is_callable($test)) {
        $test($assert);
    }
}

fwrite(STDOUT, "\n");
if ($assert->failed === 0) {
    fwrite(STDOUT, "\033[32mAll {$assert->passed} assertions passed.\033[0m\n");
    exit(0);
}

fwrite(STDOUT, "\033[31m{$assert->failed} failed, {$assert->passed} passed.\033[0m\n");
exit(1);
