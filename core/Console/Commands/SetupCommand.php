<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

/**
 * One-shot project bootstrap with a retro "VAYU SETUP" screen:
 * animated progress bar, a live task checklist and a LIVE EXECUTION panel.
 *
 *   php vayu setup            # env + key + storage + migrate
 *   php vayu setup --seed     # also run the database seeders
 *   php vayu setup --fresh    # drop all tables and migrate from scratch
 *   php vayu setup --plain    # no animation (plain log output)
 */
class SetupCommand extends Command
{
    protected static string $name = 'setup';

    protected static string $description = 'First-time setup: env, app key, storage, migrations';

    private const WIDTH = 48; // inner width of the panel boxes

    private bool $tty = false;
    private int $dynCount = 0;
    private bool $dynStarted = false;

    public function handle(array $args): int
    {
        $seed = in_array('--seed', $args, true);
        $fresh = in_array('--fresh', $args, true);
        $plain = in_array('--plain', $args, true);

        $this->tty = !$plain && $this->isTty();

        /** @var list<array{label:string, cmd:string, run:callable}> $tasks */
        $tasks = [
            [
                'label' => 'Environment & app key',
                'cmd' => 'php vayu key:generate',
                'run' => fn () => $this->ensureAppKey(),
            ],
            [
                'label' => 'Storage directories',
                'cmd' => 'mkdir -p storage/{logs,cache,uploads}',
                'run' => fn () => $this->ensureStorage(),
            ],
            [
                'label' => 'PHP extensions',
                'cmd' => 'php vayu doctor',
                'run' => fn () => $this->checkExtensions(),
            ],
            [
                'label' => $fresh ? 'Fresh migration' : 'Database migrations',
                'cmd' => $fresh ? 'php vayu migrate:fresh' : 'php vayu migrate',
                'run' => fn () => $this->spawn($fresh ? 'migrate:fresh' : 'migrate'),
            ],
        ];

        if ($seed) {
            $tasks[] = [
                'label' => 'Database seeders',
                'cmd' => 'php vayu db:seed',
                'run' => fn () => $this->spawn('db:seed'),
            ];
        }

        return $this->tty ? $this->runAnimated($tasks) : $this->runPlain($tasks);
    }

    /* ------------------------------------------------------------------ */
    /* Animated retro screen                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @param list<array{label:string, cmd:string, run:callable}> $tasks
     */
    private function runAnimated(array $tasks): int
    {
        $this->hideCursor();

        // Static header + logo (printed once).
        $this->renderHeader();

        $states = array_fill(0, count($tasks), 'queued');
        $total = count($tasks);
        $failure = null;

        $this->renderPanel($tasks, $states, 0.0, 'awaiting start');
        usleep(200_000);

        foreach ($tasks as $i => $task) {
            $states[$i] = 'installing';
            $this->renderPanel($tasks, $states, $i / $total, $task['cmd']);
            usleep(220_000);

            [$ok, $err] = ($task['run'])();

            $states[$i] = $ok ? 'ready' : 'failed';
            $this->renderPanel($tasks, $states, ($i + 1) / $total, $task['cmd']);
            usleep(160_000);

            if (!$ok) {
                $failure = [$task['label'], $err];
                break;
            }
        }

        $this->showCursor();
        $this->line('');

        if ($failure !== null) {
            [$label, $err] = $failure;
            $this->error("  ✘ {$label} failed.");
            if (is_string($err) && trim($err) !== '') {
                foreach (explode("\n", rtrim($err)) as $row) {
                    $this->dim('    ' . $row);
                }
            }
            $this->line('');

            return 1;
        }

        $this->info('  ✔ Setup complete.  Start the server with:  php vayu run');
        $this->line('');

        return 0;
    }

    /**
     * @param list<array{label:string, cmd:string, run:callable}> $tasks
     * @param list<string>                                        $states
     */
    private function renderPanel(array $tasks, array $states, float $progress, string $liveCmd): void
    {
        $w = self::WIDTH;
        $pct = (int) round($progress * 100);

        $lines = [];
        $lines[] = $this->boxTop($w);
        $lines[] = $this->boxLine("\033[1m SETUP IN PROGRESS: \033[36m{$pct}%\033[0m", " SETUP IN PROGRESS: {$pct}%", $w);
        $lines[] = $this->barLine($progress, $w);
        $lines[] = $this->boxBottom($w);
        $lines[] = '';

        foreach ($tasks as $i => $task) {
            $lines[] = $this->checklistLine($task['label'], $states[$i]);
        }

        $lines[] = '';
        $lines[] = $this->boxTitleTop('LIVE EXECUTION', $w);
        $lines[] = $this->boxLine("\033[36m {$liveCmd}\033[0m", ' ' . $liveCmd, $w);
        $lines[] = $this->boxBottom($w);

        $this->draw($lines);
    }

    /**
     * Repaint the dynamic region in place using cursor movement.
     *
     * @param list<string> $lines
     */
    private function draw(array $lines): void
    {
        $out = '';
        if ($this->dynStarted) {
            $out .= "\033[{$this->dynCount}A"; // cursor up to the top of the region
        }
        foreach ($lines as $line) {
            $out .= "\033[2K  " . $line . PHP_EOL; // clear line, 2-space margin
        }

        fwrite(STDOUT, $out);
        $this->dynCount = count($lines);
        $this->dynStarted = true;
    }

    /* ------------------------------------------------------------------ */
    /* Plain (non-TTY / --plain) fallback                                 */
    /* ------------------------------------------------------------------ */

    /**
     * @param list<array{label:string, cmd:string, run:callable}> $tasks
     */
    private function runPlain(array $tasks): int
    {
        $this->line('');
        $this->comment('  Setting up Vayu...');
        $this->line('');

        foreach ($tasks as $task) {
            $this->comment("  → {$task['label']}");
            [$ok, $err] = ($task['run'])();
            if (!$ok) {
                $this->error("    ✘ failed");
                if (is_string($err) && trim($err) !== '') {
                    $this->dim('    ' . trim($err));
                }

                return 1;
            }
            $this->info("    ✔ ready");
        }

        $this->line('');
        $this->info('  Setup complete. Start the server with:  php vayu run');
        $this->line('');

        return 0;
    }

    /* ------------------------------------------------------------------ */
    /* Tasks                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0:bool, 1:?string}
     */
    private function ensureAppKey(): array
    {
        if ($this->app->config('app.key')) {
            return [true, null];
        }

        return $this->spawn('key:generate');
    }

    /**
     * @return array{0:bool, 1:?string}
     */
    private function ensureStorage(): array
    {
        foreach (['storage/logs', 'storage/cache', 'storage/cache/data', 'storage/uploads'] as $dir) {
            $path = $this->app->basePath($dir);
            if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                return [false, "could not create {$dir}"];
            }
        }

        return [true, null];
    }

    /**
     * @return array{0:bool, 1:?string}
     */
    private function checkExtensions(): array
    {
        $missing = [];
        foreach (['pdo', 'mbstring', 'json', 'openssl'] as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = "ext-{$ext}";
            }
        }

        return $missing === []
            ? [true, null]
            : [false, 'missing required extensions: ' . implode(', ', $missing)];
    }

    /**
     * Run another vayu command as a captured subprocess so its output never
     * disturbs the animated panel.
     *
     * @return array{0:bool, 1:?string}
     */
    private function spawn(string $sub): array
    {
        $bin = $this->app->basePath('bin/vayu');
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' ' . $sub . ' 2>&1';

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        return [$code === 0, implode("\n", $output)];
    }

    /* ------------------------------------------------------------------ */
    /* Rendering helpers                                                  */
    /* ------------------------------------------------------------------ */

    private function renderHeader(): void
    {
        $this->line('');
        $this->line('  ' . $this->rule('VAYU SETUP', self::WIDTH + 2));
        $this->line('');

        $banner = [
            '██╗   ██╗ █████╗ ██╗   ██╗██╗   ██╗',
            '██║   ██║██╔══██╗╚██╗ ██╔╝██║   ██║',
            '██║   ██║███████║ ╚████╔╝ ██║   ██║',
            '╚██╗ ██╔╝██╔══██║  ╚██╔╝  ██║   ██║',
            ' ╚████╔╝ ██║  ██║   ██║   ╚██████╔╝',
            '  ╚═══╝  ╚═╝  ╚═╝   ╚═╝    ╚═════╝ ',
        ];
        foreach ($banner as $row) {
            $this->line("\033[36m    {$row}\033[0m");
        }
        $this->line('');
    }

    private function rule(string $title, int $width): string
    {
        $title = " {$title} ";
        $titleLen = mb_strlen($title);
        $side = max(0, intdiv($width - $titleLen, 2));
        $left = str_repeat('─', $side);
        $right = str_repeat('─', max(0, $width - $titleLen - $side));

        return "\033[36m{$left}\033[0m\033[1m{$title}\033[0m\033[36m{$right}\033[0m";
    }

    private function boxTop(int $w): string
    {
        return "\033[2m┌" . str_repeat('─', $w + 2) . "┐\033[0m";
    }

    private function boxBottom(int $w): string
    {
        return "\033[2m└" . str_repeat('─', $w + 2) . "┘\033[0m";
    }

    private function boxTitleTop(string $title, int $w): string
    {
        $seg = "─ {$title} ";
        $fill = max(0, ($w + 2) - mb_strlen($seg));

        return "\033[2m┌\033[0m\033[36m{$seg}\033[0m\033[2m" . str_repeat('─', $fill) . "┐\033[0m";
    }

    /**
     * A content line. $colored carries ANSI styling; $raw is the uncolored
     * text used only to measure the padding so the right border lines up.
     */
    private function boxLine(string $colored, string $raw, int $w): string
    {
        $pad = max(0, $w - mb_strlen($raw));

        return "\033[2m│\033[0m {$colored}" . str_repeat(' ', $pad) . " \033[2m│\033[0m";
    }

    private function barLine(float $progress, int $w): string
    {
        $filled = (int) round($progress * $w);
        $filled = max(0, min($w, $filled));
        $bar = "\033[36m" . str_repeat('█', $filled) . "\033[0m"
            . "\033[2m" . str_repeat('░', $w - $filled) . "\033[0m";

        // Raw width is exactly $w (one column per block glyph).
        return "\033[2m│\033[0m {$bar} \033[2m│\033[0m";
    }

    private function checklistLine(string $label, string $state): string
    {
        $label = str_pad($label, 22);

        switch ($state) {
            case 'ready':
                return "\033[32m[✓]\033[0m \033[0m{$label}\033[0m \033[32m(ready)\033[0m";
            case 'installing':
                return "\033[33m[~]\033[0m \033[1m{$label}\033[0m \033[33m(installing...)\033[0m";
            case 'failed':
                return "\033[31m[✘]\033[0m {$label} \033[31m(failed)\033[0m";
            default:
                return "\033[2m[ ] {$label} (queued)\033[0m";
        }
    }

    private function isTty(): bool
    {
        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDOUT);
        }
        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDOUT);
        }

        return false;
    }

    private function hideCursor(): void
    {
        fwrite(STDOUT, "\033[?25l");
    }

    private function showCursor(): void
    {
        fwrite(STDOUT, "\033[?25h");
    }
}
