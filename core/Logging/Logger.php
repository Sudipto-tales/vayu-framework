<?php

namespace Vayu\Logging;

/**
 * Minimal PSR-3-ish file logger with `daily` or `single` channels.
 */
class Logger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    protected string $directory;

    protected string $channel;

    public function __construct(string $directory, string $channel = 'daily')
    {
        $this->directory = rtrim($directory, '/');
        $this->channel = in_array($channel, ['daily', 'single'], true) ? $channel : 'daily';
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        if (!is_dir($this->directory) || !is_writable($this->directory)) {
            return; // never let logging break the request
        }

        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        @file_put_contents($this->file(), $line, FILE_APPEND | LOCK_EX);
    }

    protected function file(): string
    {
        $name = $this->channel === 'daily'
            ? 'vayu-' . date('Y-m-d') . '.log'
            : 'vayu.log';

        return $this->directory . '/' . $name;
    }
}
