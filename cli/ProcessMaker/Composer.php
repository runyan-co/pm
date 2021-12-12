<?php

namespace ProcessMaker\Cli;

use Exception;
use LogicException;
use RuntimeException;
use \Config as ConfigFacade;
use Illuminate\Support\Str;

class Composer
{
    public $cli, $files;

    public function __construct(CommandLine $cli, FileSystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * @param  string  $path_to_composer_json
     *
     * @return mixed
     */
    public function getComposerJson(string $path_to_composer_json)
    {
        if (!$this->files->isDir($path_to_composer_json)) {
            throw new LogicException("Path to composer.json not found: $path_to_composer_json");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "$path_to_composer_json/composer.json";

        if (!$this->files->exists($composer_json_file)) {
            throw new RuntimeException("Composer.json not found: $composer_json_file");
        }

        return json_decode($this->files->get($composer_json_file), false);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function addRepositoryPath(): void
    {
        $packagesPath = ConfigFacade::packagesPath();
        $this->cli->runCommand("composer config repositories.pm4-packages path ${packagesPath}/*", function($code, $output) {
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
        $this->cli->runCommand("composer require $packages", function($code, $output) {
            throw new Exception($output);
        }, ConfigFacade::codebasePath());
    }
}
