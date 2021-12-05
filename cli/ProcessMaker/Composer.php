<?php

namespace ProcessMaker\Cli;

use LogicException, RuntimeException;
use DomainException;
use \Git as GitFacade;
use \Packages as PackagesFacade;
use \FileSystem as FileSystemFacade;
use \CommandLine as CommandLineFacade;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class Composer
{
    public function outputPostInstallPackages(ProcessManager $processManager)
    {
        $processManager->getProcessOutput()->each(
            function (Collection $output, $command) use ($processManager) {

                if ($output->isEmpty()) {
                    return;
                }

                // Grab the exit code which ran this command
                $exitCode = $processManager->findProcessExitCode($command);

                // Output successfully run commands and the ones which failed
                if ($exitCode === 0) {
                    output("<info>Command Success:</info> $command");
                } else {
                    output("<fg=red>Command Failed:</> $command");
                }

                if ($processManager->verbose) {
                    // Removes the exit code value from the output
                    $output = $output->reject(function ($line) {
                        return is_array($line);
                    });

                    output((function () use ($output) {
                        return implode("", $output->toArray());
                    })());
                }
            });
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

        return json_decode(FileSystemFacade::get($composer_json_file), false);
    }
}
