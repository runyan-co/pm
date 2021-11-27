<?php

namespace ProcessMaker\Cli;

use Exception;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CommandLine
{
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
    function runCommand(string $command, callable $onError = null): string
    {
        $onError = $onError ? : function () {};

        $process = Process::fromShellCommandline($command);

        $processOutput = '';

        try {
            $process->mustRun(function ($type, $line) use (&$processOutput) {
                $processOutput .= $line;
            });
        } catch (ProcessFailedException $exception) {
            $onError($process->getExitCode(), $processOutput);
        }

        return $processOutput;
    }
}
