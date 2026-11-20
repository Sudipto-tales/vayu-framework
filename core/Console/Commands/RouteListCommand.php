<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

class RouteListCommand extends Command
{
    protected static string $name = 'route:list';

    protected static string $description = 'List all registered routes';

    public function handle(array $args): int
    {
        $routes = $this->app->router()->all();

        if ($routes === []) {
            $this->comment('No routes registered.');

            return 0;
        }

        $rows = [];
        foreach ($routes as $route) {
            $action = $this->describeHandler($route->handler);
            $rows[] = [
                implode('|', array_diff($route->methods, ['HEAD'])),
                $route->path,
                $route->name ?? '-',
                $action,
            ];
        }

        $this->line('');
        $this->table(['METHOD', 'URI', 'NAME', 'ACTION'], $rows);
        $this->line('');

        return 0;
    }

    protected function describeHandler(mixed $handler): string
    {
        if ($handler instanceof \Closure) {
            return 'Closure';
        }

        if (is_array($handler)) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : $handler[0];

            return $class . '@' . $handler[1];
        }

        return is_string($handler) ? $handler : 'callable';
    }
}
