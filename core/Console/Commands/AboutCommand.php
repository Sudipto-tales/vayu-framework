<?php

namespace Vayu\Console\Commands;

use Vayu\Application;
use Vayu\Console\Command;

class AboutCommand extends Command
{
    protected static string $name = 'about';

    protected static string $description = 'Display basic information about the application';

    public function handle(array $args): int
    {
        $rows = [
            ['Framework', 'Vayu ' . Application::VERSION],
            ['PHP', PHP_VERSION],
            ['App name', (string) $this->app->config('app.name', 'Vayu')],
            ['Environment', $this->app->environment()],
            ['Debug', $this->app->isDebug() ? 'enabled' : 'disabled'],
            ['App key', $this->app->config('app.key') ? 'set' : 'MISSING (run: php vayu key:generate)'],
            ['Routes', (string) count($this->app->router()->all())],
            ['Base path', $this->app->basePath()],
        ];

        $this->line('');
        foreach ($rows as [$label, $value]) {
            $this->line(sprintf("  \033[33m%-13s\033[0m %s", $label, $value));
        }
        $this->line('');

        return 0;
    }
}
