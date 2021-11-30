<?php

namespace ProcessMaker\Cli;

use LogicException, RuntimeException;
use \Packages as PackagesFacade;
use \FileSystem as FileSystemFacade;
use \CommandLine as CommandLineFacade;
use \Git as GitFacade;
use Illuminate\Support\Str;

class Composer
{
    public function installEnterprisePackages(bool $for_41_develop = false)
    {
        if (!FileSystemFacade::isDir(CODEBASE_PATH)) {
            throw new LogicException('Could not find ProcessMaker codebase: '.CODEBASE_PATH);
        }

        $branch = $for_41_develop ? '4.1-develop' : 'develop';
        $branch_switch_output = GitFacade::switchBranch($branch, CODEBASE_PATH, true);

        $install_commands = [];

        foreach (PackagesFacade::getSupportedPackages(true) as $package) {
            $commands = [
                "composer require processmaker/$package --no-interaction",
                "php artisan $package:install",
                "php artisan vendor:publish --tag=$package"
            ];

            $commands = array_map(function ($command) {
                return CommandLineFacade::transformCommandToRunAsUser($command, CODEBASE_PATH);
            }, $commands);


            $install_commands[] = $commands;
        }

        $processManager = resolve(ProcessManager::class);

        $processManager->setVerbosity(true);

        $processManager->buildProcessesBundleAndStart($install_commands);
    }

    /**
     * @param  string  $path_to_composer_json
     *
     * @return mixed
     */
    public function getComposerJson(string $path_to_composer_json)
    {
        if (!FileSystemFacade::isDir($path_to_composer_json)) {
            throw new LogicException("Path to composer.json not found: $path_to_composer_json");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "$path_to_composer_json/composer.json";

        if (!FileSystemFacade::exists($composer_json_file)) {
            throw new RuntimeException("Composer.json not found: $composer_json_file");
        }

        return json_decode(FileSystemFacade::get($composer_json_file));
    }
}
