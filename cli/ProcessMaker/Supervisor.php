<?php

namespace ProcessMaker\Cli;

use RuntimeException;
use \CommandLine as Cli;
use Illuminate\Support\Str;

class Supervisor
{
    /**
     * Checks is supervisord is running and available to receive commands
     *
     * @return bool
     */
    public function running(): bool
    {
        try {
            return is_string(Cli::runAsUser('supervisorctl status', function ($exitCode, $output) {
                // Even if one ore more of the processes have failed to
                // start or were incorrectly terminated, all we want to
                // know with this method is whether or not supervisor is
                // running at all, so as long as we receive output that
                // doesn't include a socket connection failure, we're set
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
     * @param  string|null  $process
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public function stop(string $process = null): bool
    {
        if (!$this->running()) {
            return false;
        }

        $process = $process ?? 'all';

        return is_string(Cli::runAsUser("supervisorctl stop $process", function ($exitCode, $output) {
            throw new RuntimeException($output);
        }));
    }

    /**
     * If the $process argument is present, attempt to restart a supervisor-controlled process
     * with that name, otherwise restart all supervisor processes.
     *
     * @param  string|null  $process
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public function restart(string $process = null): bool
    {
        if (!$this->running()) {
            return false;
        }

        $process = $process ?? 'all';

        $restarted = Cli::runAsUser("supervisorctl restart $process", function ($exitCode, $output) {
            throw new RuntimeException($output);
        });

        if (Str::contains($restarted, ['ERROR', 'BACKING OFF', 'FATAL'])) {
            throw new RuntimeException('One more supervisor processes could not be restarted: '.PHP_EOL.$restarted);
        }

        return true;
    }
}
