<?php

namespace ProcessMaker\Cli;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class CommandLine
{
    private $progress, $time;

    public function __construct()
    {
        $this->time = microtime(true);
    }

    /**
     * Returns the timing (in seconds) since the
     * CommandLine class was instantiated
     *
     * @return string
     */
    public function timing(): string
    {
        $seconds = round(abs($this->time - microtime(true)), 2);
        $minutes = round($seconds / 60, 2);
        $hours = round($minutes / 60, 2);
        if ($hours >= 1.00) {
            $minutes = $hours - round($hours);
            $minutes = round($minutes * 60);
            $hours = round($hours);

            return "$hours h and $minutes m";
        }
        if ($minutes >= 1.00) {
            $seconds = $minutes - round($minutes);
            $seconds = round($seconds * 60);
            $minutes = round($minutes);

            return "$minutes m and $seconds s";
        }

        return "$seconds s";
    }

    public function transformCommandToRunAsUser(string $command, string $path = null): string
    {
        return ($path ? 'cd '.$path.' && ' : '').$command;
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
     * @param  string  $type
     */
    public function createProgressBar(int $count, string $type = 'minimal'): void
    {
        $this->progress = new ProgressBar(new ConsoleOutput(), $count);
        ProgressBar::setFormatDefinition('message', '<info>%message%</info> (%percent%%)');
        ProgressBar::setFormatDefinition('minimal', 'Progress: %percent%%');
        $this->progress->setFormat($type);
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
        if (! $this->progress instanceof ProgressBar) {
            $this->createProgressBar($count);
        }

        return $this->progress;
    }

    /**
     * Run the given command as the non-root user.
     *
     * @param  string  $command
     * @param  callable|null  $onError
     * @param  string|null  $workingDir
     *
     * @return string
     */
    public function run(string $command, callable $onError = null, string $workingDir = null): string
    {
        return $this->runCommand($command, $onError, $workingDir);
    }

    /**
     * Run the given command.
     *
     * @param  string  $command
     * @param  callable|null  $onError
     * @param  string|null  $workingDir
     *
     * @return string
     */
    public function runCommand(string $command, callable $onError = null, string $workingDir = null): string
    {
        $onError = $onError
            ? : static function () {
            };
        if (method_exists(Process::class, 'fromShellCommandline')) {
            $process = Process::fromShellCommandline($command);
        } else {
            $process = new Process($command);
        }
        if ($workingDir) {
            if (is_dir($workingDir) && ! is_file($workingDir)) {
                $process->setWorkingDirectory($workingDir);
            }
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
