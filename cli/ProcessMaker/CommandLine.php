<?php

namespace ProcessMaker\Cli;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class CommandLine
{
    private $progress;

    private $timing;

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
}
