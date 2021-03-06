<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use LogicException;
use Illuminate\Support\Collection;
use ProcessMaker\Cli\Facades\CommandLine as Cli;
use React\ChildProcess\Process;

class ParallelRun
{
    /**
     * @var callable
     */
    public $finalCallback;

    /**
     * @var bool
     */
    public $verbose = false;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $processCollections;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $outputCollection;

    /**
     * @param  \ProcessMaker\Cli\CommandLine  $cli
     * @param  \Illuminate\Support\Collection  $outputCollection
     * @param  \Illuminate\Support\Collection  $processCollections
     */
    public function __construct(
        Collection $outputCollection,
        Collection $processCollections
    ) {
        $this->processCollections = $processCollections;
        $this->outputCollection = $outputCollection;
    }

    /**
     * @param  bool  $verbose
     *
     * @return void
     */
    public function setVerbosity(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * @param  callable  $callback
     *
     * @return void
     */
    public function setFinalCallback(callable $callback): void
    {
        $this->finalCallback = $callback;
    }

    /**
     * @param  string|null  $key
     *
     * @return \Illuminate\Support\Collection
     */
    public function getProcessOutput(?string $key = null): Collection
    {
        return $key ? $this->outputCollection->get($key) : $this->outputCollection;
    }

    /**
     * @param  string  $key
     * @param $output
     *
     * @return void
     */
    public function addProcessOutput(string $key, $output): void
    {
        if (!$this->getProcessOutput()->has($key)) {
             $this->getProcessOutput()->put($key, new Collection());
        }

        $this->getProcessOutput($key)->push($output);
    }

    /**
     * @param  string  $key
     *
     * @return int
     */
    public function findProcessExitCode(string $key): int
    {
        if (!$output = $this->getProcessOutput($key)) {
            return 1;
        }

        // Search through the output for the array
        // containing the exit code value
        $exitCode = $output->reject(function ($line) {
            return ! is_array($line) || ! array_key_exists('exit_code', $line);
        });

        // If we can't find it, assume the process
        // exited with a general error
        if ($exitCode->isNotEmpty()) {
            return $exitCode->flatten()->first() ?? 1;
        }

        return 1;
    }

    /**
     * @param  string  $key
     *
     * @return \Illuminate\Support\Collection
     */
    public function getProcessCollection(string $key): Collection
    {
        if (!$this->processCollections->get($key) instanceof Collection) {
             $this->processCollections->put($key, new Collection());
        }

        return $this->processCollections->get($key);
    }

    /**
     * Build the process bundle(s), set the final callback (once all
     * processes have exited) and start each bundle of processes
     *
     * @param  array  $commands
     * @param  callable|null  $callback
     *
     * @return void
     */
    public function start(array $commands, callable $callback = null): void
    {
        if (is_callable($callback)) {
            $this->setFinalCallback($callback);
        }

        $this->startProcessesBundle($this->buildProcessesBundle($commands));
    }

    /**
     * @param  array  $commands
     *
     * @return \Illuminate\Support\Collection
     */
    public function buildProcessesBundle(array $commands): Collection
    {
        $commands = array_filter($commands, static function ($command) {
            return ! is_string($command);
        });

        if (blank($commands)) {
            throw new LogicException('Commands array cannot be empty');
        }

        $bundles = collect($commands)->transform(function (array $set) {
            return collect(array_map(static function ($command) {
                return new Process($command);
            }, $set));
        });

        return $this->setExitListeners($bundles);
    }

    /**
     * @return false|mixed|void
     */
    public function callFinalCallback()
    {
        if (is_callable($this->finalCallback)) {
            return call_user_func($this->finalCallback);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection  $bundles
     *
     * @return void
     */
    public function startProcessesBundle(Collection $bundles): void
    {
        $bundles->transform(function (Collection $bundle) {
            return $this->validateBundle($bundle);
        });

        if ($bundles->isEmpty()) {
            throw new LogicException('No bundles of processes found');
        }

        $this->setProcessIndexes($bundles);

        $this->getStartProcesses($bundles)->each(function (Process $process): void {
            $this->startProcessAndPipeOutput($process);
        });
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
                $process->on('exit', function ($exitCode, $termSignal) use (&$bundle, $process, $index): void {
                    if (! $this->verbose) {
                        Cli::getProgress()->advance();
                    }

                    // Add to the "exited" process collection
                    $this->getProcessCollection('exited')->push($process);

                    // Get the info we need to output to stdout
                    $pid = $process->getPid();
                    $command = $process->getCommand();

                    if ($this->verbose) {
                        if ($exitCode === 0) {
                            output("<fg=cyan>${pid}</>: <info>${command}</info>");
                        } else {
                            output("<fg=cyan>${pid}</>: <fg=red>${command}</>");
                        }
                    }

                    // Add to the process collections
                    if ($exitCode === 0) {
                        $this->getProcessCollection('successful')->push($process);
                    } else {
                        $this->getProcessCollection('errors')->push($process);
                    }

                    // Add to the processOutput property for reading later
                    $this->addProcessOutput($process->getCommand(), ['exit_code' => $exitCode]);

                    // Find the next process to run
                    $next_process = $bundle->get($index + 1);

                    // If one exists, run it
                    if ($next_process instanceof Process) {
                        $this->startProcessAndPipeOutput($next_process);
                    }

                    // All processes are finished
                    if ($this->finished()) {
                        // Keeps the stdout clean during verbose mode
                        if (!$this->verbose) {
                            Cli::getProgress()->finish();
                            Cli::getProgress()->clear();
                        }

                        // Last but not least, run the bound callback
                        $this->callFinalCallback();
                    }
                });

                return $process;
            });
        });

        return $bundles;
    }

    /**
     * All processes have exited
     *
     * @return bool
     */
    public function finished(): bool
    {
        return $this->getProcessCollection('queued')->count() ===
               $this->getProcessCollection('exited')->count();
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
     * @return void
     */
    private function setProcessIndexes(Collection $bundles): void
    {
        $index = 0;
        $total_processes = 0;

        // Count up all of the processes
        $bundles->each(function ($bundle) use (&$total_processes): void {
            $total_processes += $bundle->count();
        });

        // Set the "process_index" property for each
        // process among each bundle
        $bundles->each(function ($bundle) use (&$index): void {
            $bundle->transform(function (Process $process) use (&$index) {
                $process->index = $index++;

                $this->getProcessCollection('queued')->push($process);

                return $process;
            });
        });

        if (! $this->verbose) {
            Cli::getProgress($total_processes);
        }
    }

    /**
     * @param  \React\ChildProcess\Process  $process
     *
     * @return void
     */
    private function startProcessAndPipeOutput(Process $process): void
    {
        if ($process->isRunning()) {
            return;
        }

        $process->start();

        $process->stdout->on('data', function ($output) use (&$process): void {
            $this->addProcessOutput($process->getCommand(), $output);
        });

        $this->getProcessCollection('started')->push($process);
    }
}
