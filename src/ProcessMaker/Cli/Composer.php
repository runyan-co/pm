<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use LogicException, RuntimeException;
use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\Config;
use ProcessMaker\Cli\Facades\CommandLine as Cli;
use ProcessMaker\Cli\Facades\FileSystem;

class Composer
{
    /**
     * @return mixed
     */
    public function getComposerJson(string $path_to_composer_json)
    {
        if (!FileSystem::is_dir($path_to_composer_json)) {
            throw new LogicException("Path to composer.json not found: {$path_to_composer_json}");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "{$path_to_composer_json}/composer.json";

        if (!FileSystem::exists($composer_json_file)) {
            throw new RuntimeException("Composer.json not found: {$composer_json_file}");
        }

        return json_decode(FileSystem::get($composer_json_file), false);
    }

    /**
     * @throws \Exception
     */
    public function addRepositoryPath(): void
    {
        $packagesPath = packages_path();

        Cli::runCommand("composer config repositories.pm4-packages path {$packagesPath}/*",
            function ($code, $output): void {
                throw new RuntimeException($output);
        }, Config::codebasePath());
    }

    /**
     * @param $packages
     *
     * @throws \Exception
     */
    public function require($packages): void
    {
        Cli::runCommand("composer require {$packages}", function ($code, $output): void {
            throw new RuntimeException($output);
        }, Config::codebasePath());
    }
}
