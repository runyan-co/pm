<?php

namespace ProcessMaker\Cli;

use Exception;
use LogicException;
use React\ChildProcess\Process;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Output\ConsoleOutput;

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

                $process->on('exit', function ($exitCode, $termSignal) use (&$bundle, $process, $index, $process_count) {

                    // Get the command run
                    $command = $process->getCommand();

                    // Return progress to stdout
                    if ($exitCode === 0) {
                        info("Process ($process->index): Success running \"$command\"");
                    } else {
                        warning("Process ($process->index): Failed running \"$command\"");
                    }

                    // Get the next process index (if one exists)
                    $next_process_index = $index + 1;

                    // Check to make sure there's another process left
                    // in the bundle to start, if so, start it
                    if ($next_process_index !== $process_count) {
                        $this->startProcessAndPipeOutput($bundle->get($next_process_index));
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

        info($this->countProcessesInBundles($bundles)." processes to run...");

        $this->getStartProcesses($bundles)->each(function (Process $process) {
            $this->startProcessAndPipeOutput($process);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection  $bundles
     *
     * @return \Illuminate\Support\Collection
     */
    private function getStartProcesses(Collection $bundles): Collection
    {
        return $this->validateBundle(
            $bundles->map(function (Collection $bundle) {
                return $bundle->first();
            })
        );
    }

    /**
     * @param  \Illuminate\Support\Collection  $bundles
     *
     * @return int
     */
    private function countProcessesInBundles(Collection $bundles): int
    {
        $index = 0;
        $total_processes = 0;

        // Count up all of the processes
        $bundles->each(function ($bundle) use (&$total_processes) {
            $total_processes = $total_processes + $bundle->count();
        });

        // Set the "process_index" property for each
        // process among each bundle
        $bundles->each(function ($bundle) use (&$index) {
            $bundle->transform(function (Process $process) use (&$index) {
                $process->index = $index++;
                return $process;
            });
        });

        return $total_processes;
    }

    /**
     * @param  \React\ChildProcess\Process  $process
     */
    private function startProcessAndPipeOutput(Process $process): void
    {
        if ($process->isRunning()) {
            return;
        }

        $process->start();

//        $process->stdout->on('error', function (Exception $exception) {
//            echo "Process failed with exception message: ".$exception->getMessage();
//        });

//        $process->stdout->on('data', function ($chunk) {
//            (new ConsoleOutput())->writeln($chunk);
//        });
    }
}
