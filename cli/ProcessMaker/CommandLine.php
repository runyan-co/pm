<?php

namespace ProcessMaker\Cli;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class CommandLine
{
    private $progressBar;

    /**
     * Simple global function to run commands.
     *
     * @param  string  $command
     *
     * @return void
     */
    function quietly(string $command)
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
    function quietlyAsUser(string $command)
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
    function passthru(string $command)
    {
        passthru($command);
    }

    /**
     * @param  int|null  $count
     *
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    public function getProgressBar(int $count = null): ProgressBar
    {
        if (!$this->progressBar instanceof ProgressBar) {
            $this->progressBar = new ProgressBar(new ConsoleOutput(), $count);
            $this->progressBar->setRedrawFrequency(25);
            $this->progressBar->minSecondsBetweenRedraws(0.025);
            $this->progressBar->maxSecondsBetweenRedraws(0.05);
        }

        return $this->progressBar;
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
