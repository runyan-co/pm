<?php

namespace ProcessMaker\Cli;

use Exception;
use LogicException;
use RuntimeException;
use \FileSystem as Fs;
use \CommandLine as Cli;
use \Config as ConfigFacade;
use Illuminate\Support\Str;

class Composer
{
    /**
     * @param  string  $path_to_composer_json
     *
     * @return mixed
     */
    public function getComposerJson(string $path_to_composer_json)
    {
        if (!Fs::isDir($path_to_composer_json)) {
            throw new LogicException("Path to composer.json not found: $path_to_composer_json");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "$path_to_composer_json/composer.json";

        if (!Fs::exists($composer_json_file)) {
            throw new RuntimeException("Composer.json not found: $composer_json_file");
        }

        return json_decode(Fs::get($composer_json_file), false);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function addRepositoryPath(): void
    {
        $packagesPath = ConfigFacade::packagesPath();
        Cli::runCommand("composer config repositories.pm4-packages path ${packagesPath}/*", function($code, $output) {
            throw new Exception($output);
        }, ConfigFacade::codebasePath());
    }

    /**
     * @param $packages
     *
     * @return void
     * @throws \Exception
     */
    public function require($packages): void
    {
        Cli::runCommand("composer require $packages", function($code, $output) {
            throw new Exception($output);
        }, ConfigFacade::codebasePath());
    }
}
