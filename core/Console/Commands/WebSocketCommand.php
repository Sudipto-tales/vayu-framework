<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;
use Vayu\Realtime\WebSocketServer;

/**
 * Start / control the Workerman real-time server.
 *
 *   php vayu websocket                 start in the foreground
 *   php vayu websocket start -d        start as a daemon
 *   php vayu websocket stop            stop the daemon
 *   php vayu websocket reload          graceful reload
 *   php vayu websocket status          runtime status
 *   php vayu websocket --port=9000     override the listen port
 */
class WebSocketCommand extends Command
{
    protected static string $name = 'websocket';

    protected static string $description = 'Start the Workerman real-time WebSocket server';

    /** @var list<string> */
    protected array $workermanActions = ['start', 'stop', 'restart', 'reload', 'status', 'connections'];

    public function handle(array $args): int
    {
        $action = 'start';
        $daemon = false;
        $host = null;
        $port = null;

        foreach ($args as $arg) {
            if ($arg === '-d' || $arg === '--daemon') {
                $daemon = true;
            } elseif (str_starts_with($arg, '--host=')) {
                $host = substr($arg, 7);
            } elseif (str_starts_with($arg, '--port=')) {
                $port = (int) substr($arg, 7);
            } elseif (in_array($arg, $this->workermanActions, true)) {
                $action = $arg;
            }
        }

        if (!class_exists(\Workerman\Worker::class)) {
            $this->error('Workerman is not installed, so the real-time server cannot start.');
            $this->comment('Install it with:');
            $this->line('    composer require workerman/workerman');
            return 1;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->error('The Workerman server needs the pcntl/posix extensions and does not run on Windows.');
            return 1;
        }

        /** @var array<string, mixed> $config */
        $config = (array) $this->app->config('websocket', []);
        if ($host !== null) {
            $config['host'] = $host;
        }
        if ($port !== null) {
            $config['port'] = $port;
        }

        // Runtime files for daemon control (pid/log).
        $runtime = $this->app->storagePath('workerman');
        if (!is_dir($runtime)) {
            @mkdir($runtime, 0o775, true);
        }
        \Workerman\Worker::$pidFile = $runtime . '/vayu-ws.pid';
        \Workerman\Worker::$logFile = $runtime . '/vayu-ws.log';
        if ($daemon) {
            \Workerman\Worker::$daemonize = true;
        }

        // Workerman inspects the global $argv for its command verb.
        $GLOBALS['argv'] = array_values(array_filter([
            'vayu',
            $action,
            $daemon ? '-d' : null,
        ]));
        $_SERVER['argv'] = $GLOBALS['argv'];
        $GLOBALS['argc'] = count($GLOBALS['argv']);

        if ($action === 'start' && !$daemon) {
            $this->info(sprintf('Vayu real-time server  →  ws://%s:%d', $config['host'], $config['port']));
            $this->dim(sprintf(
                'Broadcast bridge (internal, loopback)  →  %s:%d',
                $config['broadcast_host'] ?? '127.0.0.1',
                $config['broadcast_port'] ?? 2207
            ));
            if (!empty($config['handler']) && is_string($config['handler'])) {
                $this->dim('Handler: ' . $config['handler']);
            }
            $this->dim('Press Ctrl+C to stop.');
            $this->line('');
        }

        (new WebSocketServer($this->app, $config))->start();

        return 0;
    }
}
