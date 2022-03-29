<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Exception;
use LogicException;
use RuntimeException;
use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\Config;

class Composer
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
     * @var
     */
    public $config;

    /**
     * @param  \ProcessMaker\Cli\CommandLine  $cli
     * @param  \ProcessMaker\Cli\FileSystem  $files
     */
    public function __construct(CommandLine $cli, FileSystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * @return mixed
     */
    public function getComposerJson(string $path_to_composer_json)
    {
        if (! $this->files->is_dir($path_to_composer_json)) {
            throw new LogicException("Path to composer.json not found: ${path_to_composer_json}");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "${path_to_composer_json}/composer.json";

        if (! $this->files->exists($composer_json_file)) {
            throw new RuntimeException("Composer.json not found: ${composer_json_file}");
        }

        return json_decode($this->files->get($composer_json_file), false);
    }

    /**
     * @throws \Exception
     */
    public function addRepositoryPath(): void
    {
        $packagesPath = Config::packagesPath();
        $this->cli->runCommand("composer config repositories.pm4-packages path ${packagesPath}/*", function ($code, $output): void {
            throw new Exception($output);
        }, Config::codebasePath());
    }

    /**
     * @param $packages
     *
     * @throws \Exception
     */
    public function require($packages): void
    {
        $this->cli->runCommand("composer require ${packages}", function ($code, $output): void {
            throw new RuntimeException($output);
        }, Config::codebasePath());
    }
}
