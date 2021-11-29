<?php

namespace ProcessMaker\Cli;

use LogicException;
use React\ChildProcess\Process;
use Illuminate\Support\Collection;

class ProcessManager
{
    private $processCollections;

    private $verbose = false;

    private $cli;

    public $finalCallback;

    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    public function setVerbosity(bool $verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @param  string  $key
     *
     * @return Collection
     */
    protected function getProcessCollections(string $key): Collection
    {
        if (!$this->processCollections instanceof Collection) {
            $this->processCollections = new Collection();
        }

        if (!$this->processCollections->get($key) instanceof Collection) {
            $this->processCollections->put($key, new Collection());
        }

        return $this->processCollections->get($key);
    }

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
     *
     * @return \Illuminate\Support\Collection
     */
    private function setExitListeners(Collection $bundles): Collection
    {
        // Set each Process to start the next process
        // in the bundle when it exists
        $bundles->each(function (Collection $bundle) {
            return $bundle->transform(function (Process $process, $index) use (&$bundle) {

                $process->on('exit', function ($exitCode, $termSignal) use (&$bundle, $process, $index) {

                    $this->cli->getProgress()->advance();

                    // Add to the "exited" process collection
                    $this->getProcessCollections('exited')->push($process);

                    // Add to the process collections
                    if ($exitCode === 0) {
                        $this->getProcessCollections('successful')->push($process);
                    } else {
                        $this->getProcessCollections('errors')->push($process);
                    }

                    // Get the command run
                    $command = $process->getCommand();

                    // Return progress to stdout
                    if ($this->verbose) {
                        if ($exitCode === 0) {
                            info("Process ($process->index): Success running \"$command\"");
                        } else {
                            warning("Process ($process->index): Failed running \"$command\"");
                        }
                    }

                    $next_process = $bundle->get($index + 1);

                    if ($next_process instanceof Process) {
                        $this->startProcessAndPipeOutput($next_process);
                    }

                    $queued = $this->getProcessCollections('queued')->count();
                    $exited = $this->getProcessCollections('exited')->count();

                    // All processes are finished
                    if ($queued === $exited) {
                        $this->cli->getProgress()->finish();
                        $this->getFinalCallback();
                    }
                });

                return $process;
            });
        });

        return $bundles;
    }

    public function getFinalCallback()
    {
        if (is_callable($this->finalCallback)) {
            return call_user_func($this->finalCallback);
        }
    }

    public function setFinalCallback(callable $callback)
    {
        $this->finalCallback = $callback;
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

                $this->getProcessCollections('queued')->push($process);

                return $process;
            });
        });

        $this->cli->getProgress($total_processes);

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

        $this->getProcessCollections('started')->push($process);
    }
}
