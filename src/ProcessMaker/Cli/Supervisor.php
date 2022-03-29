<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use RuntimeException;
use Illuminate\Support\Str;

class Supervisor
{
    /**
     * @var \ProcessMaker\Cli\CommandLine
     */
    protected $cli;

    /**
     * @param  \ProcessMaker\Cli\CommandLine  $cli
     */
    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    /**
     * Checks is supervisord is running and available to receive commands
     */
    public function running(): bool
    {
        try {
            return is_string($this->cli->run('supervisorctl status', function ($exitCode, $output): void {
                if (Str::contains($output, 'refused connection')) {
                    throw new RuntimeException();
                }

                if (Str::contains($output, 'command not found')) {
                    throw new RuntimeException();
                }
            }));
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    /**
     * If the $process argument is present, attempt to stop a supervisor-controlled process
     * with that name, otherwise stop all supervisor processes.
     *
     * @throws \RuntimeException
     */
    public function stop(?string $process = null): bool
    {
        if (!$this->running()) {
            return false;
        }

        $process = $process ?? 'all';

        return is_string($this->cli->run("supervisorctl stop {$process}", function ($exitCode, $output): void {
            throw new RuntimeException($output);
        }));
    }

    /**
     * If the $process argument is present, attempt to restart a supervisor-controlled process
     * with that name, otherwise restart all supervisor processes.
     *
     * @throws \RuntimeException
     */
    public function restart(?string $process = null): bool
    {
        if (!$this->running()) {
            return false;
        }

        $process = $process ?? 'all';

        $restarted = $this->cli->run("supervisorctl restart {$process}", function ($exitCode, $output): void {
            throw new RuntimeException($output);
        });

        if (Str::contains($restarted, ['ERROR', 'BACKING OFF', 'FATAL'])) {
            throw new RuntimeException('One more supervisor processes could not be restarted: '.PHP_EOL.$restarted);
        }

        return true;
    }
}
