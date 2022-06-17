<?php

namespace ProcessMaker\Cli;

use ProcessMaker\Cli\Facades\Composer;
use ProcessMaker\Cli\Facades\FileSystem;
use ProcessMaker\Cli\Facades\Supervisor;
use ProcessMaker\Cli\Facades\Git;
use ProcessMaker\Cli\Facades\IDE;
use Illuminate\Support\Str;

class Core
{
    /**
     * @var bool
     */
    public static $shouldRestartSupervisor = false;

    /**
     * Clone down the most recent version of processmaker/processmaker
     *
     * @return void
     */
    public function clone()
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
        register_shutdown_function(function () use ($ide) {
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
     * Local copy of processmaker/processmaker is version 4.1.*
     *
     * @return bool
     */
    public function is41()
    {
        $json = Composer::getComposerJson(codebase_path());

        return Str::startsWith($json->version, '4.1');
    }

    /**
     * Local copy of processmaker/processmaker is version 4.2.*
     *
     * @return bool
     */
    public function is42()
    {
        $json = Composer::getComposerJson(codebase_path());

        return Str::startsWith($json->version, '4.2');
    }

    /**
     * Returns true if core is already installed, false if not
     *
     * @return bool
     */
    public function isCloned(): bool
    {
        return FileSystem::exists(codebase_path('composer.json'));
    }

    /**
     * Returns true if core is already installed, false if not
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return FileSystem::exists(codebase_path('.env'));
    }
}
