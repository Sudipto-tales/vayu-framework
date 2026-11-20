<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;
use Vayu\Console\Support\Network;
use Vayu\Console\Support\QrCode;

class RunCommand extends Command
{
    protected static string $name = 'run';

    protected static string $description = 'Run the application on the PHP development server';

    public function handle(array $args): int
    {
        // Bind to all interfaces by default so the LAN preview actually works;
        // the Local URL still shows the loopback address.
        $host = '0.0.0.0';
        $port = '8000';
        $showQr = true;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--host=')) {
                $host = substr($arg, 7);
            } elseif (str_starts_with($arg, '--port=')) {
                $port = substr($arg, 7);
            } elseif ($arg === '--no-qr') {
                $showQr = false;
            }
        }

        $docroot = $this->app->publicPath();
        $router = $this->app->publicPath('index.php');

        $exposed = in_array($host, ['0.0.0.0', '::', ''], true);
        $localHost = $exposed ? '127.0.0.1' : $host;
        $localUrl = "http://{$localHost}:{$port}";
        $lanIp = $exposed ? Network::localIp() : (Network::isPrivateIpv4($host) ? $host : null);
        $networkUrl = $lanIp ? "http://{$lanIp}:{$port}" : null;

        $this->banner();
        $this->line('');
        $this->arrow('Local', $localUrl);
        if ($networkUrl !== null) {
            $this->arrow('Network', $networkUrl);
        } elseif ($exposed) {
            $this->arrow('Network', 'no LAN address detected', true);
        } else {
            $this->dim("  \xE2\x9E\x9C  Network:  pass --host=0.0.0.0 to expose on your LAN");
        }
        $this->line('');

        // Network QR — scan it from a phone on the same Wi-Fi.
        if ($showQr && $networkUrl !== null) {
            $this->qr($networkUrl);
        }

        $this->dim('  Document root: ' . $docroot);
        $this->dim('  Press Ctrl+C to stop.');
        $this->line('');

        $command = sprintf(
            '%s -S %s:%s -t %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docroot),
            escapeshellarg($router)
        );

        passthru($command, $exitCode);

        return $exitCode;
    }

    private function banner(): void
    {
        $version = \Vayu\Application::VERSION;
        $this->line('');
        $this->line("\033[36m  \xE2\x96\xB2 Vayu\033[0m  \033[2mdev server \033[0m\033[36m{$version}\033[0m");
    }

    private function arrow(string $label, string $value, bool $muted = false): void
    {
        $arrow = "\033[32m\xE2\x9E\x9C\033[0m"; // green ➜
        $name = str_pad($label . ':', 9);
        $val = $muted
            ? "\033[2m{$value}\033[0m"
            : "\033[36m{$value}\033[0m";
        $this->line("  {$arrow}  \033[1m{$name}\033[0m {$val}");
    }

    private function qr(string $url): void
    {
        try {
            $qr = QrCode::encode($url, QrCode::ECC_MEDIUM);
        } catch (\Throwable $e) {
            return; // never let QR rendering break the server launch
        }

        $this->dim('  Scan to open on your phone (same Wi-Fi):');
        $this->line('');
        foreach (explode(PHP_EOL, $qr->toTerminal()) as $row) {
            $this->line('  ' . $row);
        }
        $this->line('');
    }
}
