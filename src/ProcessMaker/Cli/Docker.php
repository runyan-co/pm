<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use RuntimeException;
use ProcessMaker\Cli\Facades\CommandLine as Cli;

class Docker
{
    /**
     * Path to docker executable
     *
     * @var string
     */
    protected $executable;

    public function __construct()
    {
        $this->executable = Cli::findExecutable('docker');
    }

    /**
     * Docker daemon state
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        try {
            return is_string(
                Cli::run($this->executable.' container ls', static function ($exitCode, $output) {
                    throw new RuntimeException;
                })
            );
        } catch (RuntimeException $exception) {
            return false;
        }
    }
}
