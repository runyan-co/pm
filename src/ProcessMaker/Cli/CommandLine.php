<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\SnapshotsRepository as Snapshots;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Process;

class CommandLine
{
    /**
     * @var ProgressBar
     */
    private $progress;

    /**
     * Returns absolute path of an executable by name
     */
    public function findExecutable(string $executable_name): string
    {
        return Str::replace([PHP_EOL, "\n"], '', $this->run("which {$executable_name}"));
    }

    /**
     * Simple global function to run commands.
     */
    public function quietly(string $command): void
    {
        $this->runCommand($command.' > /dev/null 2>&1');
    }

    /**
     * Pass the command to the command line and display the output.
     */
    public function passthru(string $command): void
    {
        passthru($command);
    }

    /**
     * Create a ProgressBar bound to this class instance
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
    public function getProgress(?int $count = null): ProgressBar
    {
        if (! $this->progress instanceof ProgressBar) {
            $this->createProgressBar($count);
        }

        return $this->progress;
    }

    /**
     * Run the given command as the non-root user.
     */
    public function run(string $command, ?callable $onError = null, ?string $workingDir = null): string
    {
        return $this->runCommand($command, $onError, $workingDir);
    }

    /**
     * Run the given command.
     */
    public function runCommand(string $command, ?callable $onError = null, ?string $workingDir = null): string
    {
        $onError = $onError ?: static function (): void {};

        $processOutput = '';

        if (method_exists(Process::class, 'fromShellCommandline')) {
            $process = Process::fromShellCommandline($command);
        } else {
            $process = new Process($command);
        }

        if (is_string($workingDir) && is_dir($workingDir) && ! is_file($workingDir)) {
            $process->setWorkingDirectory($workingDir);
        }

        $runCommand = static function () use (&$process, &$processOutput) {
            $process->setTimeout(null)->run(
                function ($type, $line) use (&$processOutput) {
                    $processOutput .= $line;
                });
        };

        if (Snapshots::isEnabled()) {
            Snapshots::startSnapshot($command, $runCommand);
        } else {
            $runCommand();
        }

        if ($process->getExitCode() > 0) {
            $onError($process->getExitCode(), $processOutput);
        }

        return $processOutput;
    }
}
