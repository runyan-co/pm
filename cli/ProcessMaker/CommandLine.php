<?php

namespace ProcessMaker\Cli;

use LogicException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Process;
use Illuminate\Support\Collection;

class CommandLine
{
    private $progress;

    private $timing;

    private $queue;

    private $commands;

    private $output;

    private $max = 5;

    public function __construct()
    {
        $this->timing = microtime(true);
    }

    /**
     * Returns the timing (in seconds) since the
     * CommandLine class was instantiated
     *
     * @return string
     */
    public function timing(): string
    {
        return round(abs($this->timing - microtime(true)), 2) . ' seconds';
    }

    /**
     * Simple global function to run commands.
     *
     * @param  string  $command
     *
     * @return void
     */
    public function quietly(string $command)
    {
        $this->runCommand($command.' > /dev/null 2>&1');
    }

    /**
     * Simple global function to run commands.
     *
     * @param  string  $command
     *
     * @return void
     */
    public function quietlyAsUser(string $command)
    {
        $this->quietly('sudo -u "'.user().'" '.$command.' > /dev/null 2>&1');
    }

    /**
     * Pass the command to the command line and display the output.
     *
     * @param  string  $command
     *
     * @return void
     */
    public function passthru(string $command)
    {
        passthru($command);
    }

    /**
     * Create a ProgressBar bound to this class instance
     *
     * @param  int  $count
     */
    private function createProgressBar(int $count)
    {
        $this->progress = new ProgressBar(new ConsoleOutput(), $count);

        $this->progress->setRedrawFrequency(25);
        $this->progress->minSecondsBetweenRedraws(0.025);
        $this->progress->maxSecondsBetweenRedraws(0.05);
    }

    /**
     * @param  int|null  $count
     *
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    public function getProgress(int $count = null): ProgressBar
    {
        if (!$this->progress instanceof ProgressBar) {
            $this->createProgressBar($count);
        }

        return $this->progress;
    }

    /**
     * Run the given command as the non-root user.
     *
     * @param  string  $command
     * @param  callable|null  $onError
     *
     * @return string
     */
    function run(string $command, callable $onError = null): string
    {
        return $this->runCommand($command, $onError);
    }

    /**
     * Run the given command.
     *
     * @param  string  $command
     * @param  callable|null  $onError
     *
     * @return string
     */
    function runAsUser(string $command, callable $onError = null): string
    {
        return $this->runCommand('sudo -u "'.user().'" '.$command, $onError);
    }

    /**
     * Run the given command.
     *
     * @param  string  $command
     * @param  callable|null  $onError
     *
     * @return string
     */
    function runCommand(string $command, callable $onError = null)
    {
        $onError = $onError ? : function () {};

        if (method_exists(Process::class, 'fromShellCommandline')) {
            $process = Process::fromShellCommandline($command);
        } else {
            $process = new Process($command);
        }

        $processOutput = '';
        $process->setTimeout(null)->run(function ($type, $line) use (&$processOutput) {
            $processOutput .= $line;
        });

        if ($process->getExitCode() > 0) {
            $onError($process->getExitCode(), $processOutput);
        }

        return $processOutput;
    }

    /**
     * Start a given Process and bind the output to this instance
     *
     * @param  \Symfony\Component\Process\Process  $process
     */
    private function startProcess(Process $process)
    {
        if ($process->isRunning()) {
            return;
        }

        $process->start(function ($type, $line) {
            $this->getOutput()->writeln($line);
        });
    }

    public function getOutput()
    {
        if ($this->output instanceof ConsoleOutput) {
            return $this->output;
        }

        $this->output = new ConsoleOutput();

        return $this->output;
    }

    private function setProcessQueue(array $commands, int $max)
    {
        $this->commands = collect($commands)->reject(function ($command) {
            return ! is_string($command);
        });

        $this->max = min(abs($max), $this->commands->count());

        $this->queue = $this->commands->shift($this->max)->map(function ($command) {
            return Process::fromShellCommandline($command);
        });
    }

    private function getProcessQueue(): Collection
    {
        if (! $this->queue instanceof Collection) {
            throw new LogicException('Process queue not found');
        }

        if (! $this->commands instanceof Collection) {
            throw new LogicException('Commands to for Process queue not found');
        }

        return $this->queue;
    }

    /**
     * Run multiple Processes in parallel
     *
     * @param  array  $commands
     * @param  int  $maxParallel
     * @param  int  $poll
     */
    public function runParallel(array $commands, int $maxParallel = 5, int $poll = 1000)
    {
        $this->setProcessQueue($commands, $maxParallel);

        // start the initial stack of processes
        $this->getProcessQueue()->each(function ($process) {
            $this->startProcess($process);
        });

        do {
            usleep($poll);

            $this->queue = $this->queue->reject(function (Process $process) {
                return ! $process->isRunning();
            });

            if ($this->commands->isEmpty()) {
                return;
            }

            $nextProcess = Process::fromShellCommandline($this->commands->shift());

            $this->startProcess($nextProcess);

            $this->queue->add($nextProcess);

        } while ($this->queue->isNotEmpty() || $this->commands->isNotEmpty());

//        do {
//            // wait for the given time
//            usleep($poll);
//
//            // remove all finished processes from the stack
//            foreach ($currentProcesses as $index => $process) {
//                if (!$process->isRunning()) {
//                    unset($currentProcesses[$index]);
//
//                    // directly add and start new process after the previous finished
//                    if (count($processesQueue) > 0) {
//                        $nextProcess = array_shift($processesQueue);
//                        $this->startProcess($process);
//                        $currentProcesses[] = $nextProcess;
//                    }
//                }
//            }
//            // continue loop while there are processes being executed or waiting for execution
//        } while (count($processesQueue) > 0 || count($currentProcesses) > 0);
    }
}
