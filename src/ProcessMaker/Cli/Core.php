<?php

namespace ProcessMaker\Cli;

use ProcessMaker\Cli\Facades\CommandLine;
use ProcessMaker\Cli\Facades\Composer;
use ProcessMaker\Cli\Facades\FileSystem;
use ProcessMaker\Cli\Facades\Supervisor;
use ProcessMaker\Cli\Facades\Git;
use ProcessMaker\Cli\Facades\IDE;
use Illuminate\Support\Str;

class Core
{
    /**
     * @var \ProcessMaker\Cli\CommandLine
     */
    public $cli;

    /**
     * @var \ProcessMaker\Cli\FileSystem
     */
    public $files;

    /**
     * @var \ProcessMaker\Cli\Composer
     */
    public $composer;

    /**
     * @var bool
     */
    public static $shouldRestartSupervisor = false;

    /**
     * @param  \ProcessMaker\Cli\Facades\CommandLine  $cli
     * @param  \ProcessMaker\Cli\FileSystem  $files
     * @param  \ProcessMaker\Cli\Facades\Composer  $composer
     */
    public function __construct(
        CommandLine $cli,
        FileSystem $files,
        Composer $composer)
    {
        $this->cli = $cli;
        $this->files = $files;
        $this->composer = $composer;
    }

    /**
     * Clone down the most recent version of processmaker/processmaker
     *
     * @return void
     */
    public function install()
    {
        // Save any IDE config files
        if ($ide = IDE::hasConfiguration()) {
            IDE::temporarilyMoveConfiguration();
        }

        // The steps increase by 2, for example, if supervisor
        // is running since we need to stop it before executing
        // the commands, then restart it when were finished
        if (self::$shouldRestartSupervisor = Supervisor::running()) {
            Supervisor::stop();
        }

        // Make sure we re-add the IDE settings
        // in case of a premature shutdown
        register_shutdown_function(static function () use ($ide) {
            if (is_string($ide) && FileSystem::exists($ide)) {
                IDE::moveConfigurationBack($ide);
            }
        });

        // Remove old codebase
        FileSystem::rmdir($codebase = codebase_path());

        // Clone a fresh copy
        Git::clone('processmaker', Str::replaceLast('processmaker', '', $codebase));

        // Re-add the IDE settings (if they existed to begin with)
        if ($ide) {
            IDE::moveConfigurationBack($ide);
        }
    }

    /**
     * Returns true if core is already installed, false if not
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return $this->files->exists($env = codebase_path('.env'));
    }
}
