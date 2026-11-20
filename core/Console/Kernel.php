<?php

namespace Vayu\Console;

use Vayu\Application;
use Vayu\Console\Commands\AboutCommand;
use Vayu\Console\Commands\CacheClearCommand;
use Vayu\Console\Commands\ConfigCacheCommand;
use Vayu\Console\Commands\ConfigClearCommand;
use Vayu\Console\Commands\DbSeedCommand;
use Vayu\Console\Commands\DoctorCommand;
use Vayu\Console\Commands\KeyGenerateCommand;
use Vayu\Console\Commands\MakeMigrationCommand;
use Vayu\Console\Commands\MakeModelCommand;
use Vayu\Console\Commands\MakeSeederCommand;
use Vayu\Console\Commands\MigrateCommand;
use Vayu\Console\Commands\MigrateFreshCommand;
use Vayu\Console\Commands\MigrateRollbackCommand;
use Vayu\Console\Commands\MigrateStatusCommand;
use Vayu\Console\Commands\OptimizeClearCommand;
use Vayu\Console\Commands\OptimizeCommand;
use Vayu\Console\Commands\RouteListCommand;
use Vayu\Console\Commands\RunCommand;
use Vayu\Console\Commands\SetupCommand;
use Vayu\Console\Commands\WebSocketCommand;

/**
 * Console kernel: resolves the requested command and runs it.
 */
class Kernel
{
    /** @var list<class-string<Command>> */
    protected array $commands = [
        SetupCommand::class,
        RunCommand::class,
        WebSocketCommand::class,
        AboutCommand::class,
        DoctorCommand::class,
        RouteListCommand::class,
        KeyGenerateCommand::class,
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        MigrateStatusCommand::class,
        MigrateFreshCommand::class,
        DbSeedCommand::class,
        CacheClearCommand::class,
        ConfigCacheCommand::class,
        ConfigClearCommand::class,
        OptimizeCommand::class,
        OptimizeClearCommand::class,
        MakeModelCommand::class,
        MakeMigrationCommand::class,
        MakeSeederCommand::class,
    ];

    public function __construct(protected Application $app)
    {
    }

    /**
     * @param list<string> $argv
     */
    public function handle(array $argv): int
    {
        $name = $argv[1] ?? 'list';
        $args = array_slice($argv, 2);

        if (in_array($name, ['list', '--help', '-h', 'help'], true)) {
            $this->listCommands();

            return 0;
        }

        foreach ($this->commands as $class) {
            if ($class::name() === $name) {
                return (new $class($this->app))->handle($args);
            }
        }

        fwrite(STDERR, "\033[31mUnknown command: {$name}\033[0m" . PHP_EOL);
        $this->listCommands();

        return 1;
    }

    protected function listCommands(): void
    {
        $version = Application::VERSION;
        fwrite(STDOUT, "\033[36m");
        fwrite(STDOUT, <<<BANNER

  ╦  ╦╔═╗╦ ╦╦ ╦
  ╚╗╔╝╠═╣╚╦╝║ ║   Vayu Framework {$version}
   ╚╝ ╩ ╩ ╩ ╚═╝

BANNER);
        fwrite(STDOUT, "\033[0m");
        fwrite(STDOUT, "\033[33m  Usage:\033[0m  php vayu <command> [options]" . PHP_EOL . PHP_EOL);
        fwrite(STDOUT, "\033[33m  Available commands:\033[0m" . PHP_EOL);

        foreach ($this->commands as $class) {
            fwrite(STDOUT, sprintf(
                "    \033[32m%-16s\033[0m %s" . PHP_EOL,
                $class::name(),
                $class::description()
            ));
        }
        fwrite(STDOUT, PHP_EOL);
    }
}
