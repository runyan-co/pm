<?php

namespace ProcessMaker\Cli;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class CommandLine
{
    private $progress, $output;

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
     * Get the console output for this instance
     *
     * @return \Symfony\Component\Console\Output\ConsoleOutput
     */
    public function getOutput(): ConsoleOutput
    {
        if (!$this->output instanceof ConsoleOutput) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
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

    /**
     * Run multiple Processes in parallel
     *
     * @param  array  $processes
     * @param  int $maxParallel
     * @param  int  $poll
     */
    public function runParallel(array $processes, int $maxParallel, int $poll = 250)
    {
        // do not modify the object pointers in the argument, copy to local working variable
        $processesQueue = array_filter($processes, function ($process) {
            return $process instanceof Process;
        });

        // fix maxParallel to be max the number of processes or positive
        $maxParallel = min(abs($maxParallel), count($processesQueue));

        // get the first stack of processes to start at the same time
        /** @var Process[] $currentProcesses */
        $currentProcesses = array_splice($processesQueue, 0, $maxParallel);

        // start the initial stack of processes
        foreach ($currentProcesses as $process) {
            $this->startProcess($process);
        }

        do {
            // wait for the given time
            usleep($poll);

            // remove all finished processes from the stack
            foreach ($currentProcesses as $index => $process) {
                if (!$process->isRunning()) {
                    unset($currentProcesses[$index]);

                    // directly add and start new process after the previous finished
                    if (count($processesQueue) > 0) {
                        $nextProcess = array_shift($processesQueue);
                        $this->startProcess($process);
                        $currentProcesses[] = $nextProcess;
                    }
                }
            }
            // continue loop while there are processes being executed or waiting for execution
        } while (count($processesQueue) > 0 || count($currentProcesses) > 0);
    }
}
