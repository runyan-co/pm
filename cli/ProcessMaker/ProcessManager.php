<?php

namespace ProcessMaker\Cli;

use LogicException;
use React\ChildProcess\Process;
use Illuminate\Support\Collection;

class ProcessManager
{
    /**
     * @param  array  $commands
     */
    public function buildProcessesBundleAndStart(array $commands)
    {
        $this->startProcessesBundle($this->buildProcessesBundle($commands));
    }

    /**
     * @param  array  $commands
     *
     * @return \Illuminate\Support\Collection
     */
    public function buildProcessesBundle(array $commands): Collection
    {
        $commands = array_filter($commands, function ($command) {
            return !is_string($command);
        });

        if (blank($commands)) {
            throw new LogicException('Commands array cannot be empty');
        }

        $bundles = collect($commands)->transform(function (array $set) {
            return collect(array_map(function ($command) {
                return new Process($command);
            }, $set));
        });

        return $this->setExitListeners($bundles);
    }

    /**
     * @param  \Illuminate\Support\Collection  $bundles
     * @param  callable|null  $onExit
     *
     * @return \Illuminate\Support\Collection
     */
    private function setExitListeners(Collection $bundles): Collection
    {
        // Set each Process to start the next process
        // in the bundle when it exists
        $bundles->each(function (Collection $bundle) {
            return $bundle->transform(function (Process $process, $index) use (&$bundle) {

                // Number of processes in this bundle
                $process_count = $bundle->count();

                $process->on('exit', function ($exitCode, $termSignal) use (&$bundle, $index, $process_count) {

                    // Return progress to stdout
                    if ($exitCode === 0) {
                        info("Process $index finished successfully!");
                    } else {
                        warning("Process $index failed with exit code: $exitCode");
                    }

                    // Get the next process index (if one exists)
                    $next_process_index = $index + 1;

                    // Check to make sure there's another process left
                    // in the bundle to start, if so, start it
                    if ($next_process_index !== $process_count) {
                        $bundle->get($next_process_index)->start();
                    }
                });

                return $process;
            });
        });

        return $bundles;
    }

    /**
     * @param  \Illuminate\Support\Collection  $bundle
     *
     * @return \Illuminate\Support\Collection
     */
    private function validateBundle(Collection $bundle): Collection
    {
        return $bundle->reject(function ($process) {
            return ! $process instanceof Process;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection  $bundles
     */
    public function startProcessesBundle(Collection $bundles)
    {
        $bundles->transform(function (Collection $bundle) {
            return $this->validateBundle($bundle);
        });

        if ($bundles->isEmpty()) {
            throw new LogicException('No bundles of Processes found');
        }

        $total_processes_to_run = 0;

        $bundles->each(function ($bundle) use (&$total_processes_to_run) {
            $total_processes_to_run = $total_processes_to_run + $bundle->count();
        });

        info("$total_processes_to_run process to run...");

        $start_processes = $this->validateBundle(
            $bundles->map(function (Collection $bundle) {
                return $bundle->first();
            })
        );

        $start_processes->each(function (Process $process) {
            $process->start();
        });
    }
}
