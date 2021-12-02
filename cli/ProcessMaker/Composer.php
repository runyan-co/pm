<?php

namespace ProcessMaker\Cli;

use LogicException, RuntimeException;
use \Git as GitFacade;
use \Packages as PackagesFacade;
use \FileSystem as FileSystemFacade;
use \CommandLine as CommandLineFacade;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class Composer
{
    public function buildComposerRequireAndInstallPackagesCommands(bool $for_41_develop = false): Collection
    {
        if (!FileSystemFacade::isDir(CODEBASE_PATH)) {
            throw new LogicException('Could not find ProcessMaker codebase: '.CODEBASE_PATH);
        }

        // Find out which branch to switch to in the local
        // processmaker/processmaker codebase
        $branch = $for_41_develop ? '4.1-develop' : 'develop';

        // Attempt to switch to the desired branch
        $branch_switch_output = GitFacade::switchBranch($branch, CODEBASE_PATH, true);

        // Grab the list of supported enterprise packages
        $enterprise_packages = new Collection(PackagesFacade::getSupportedPackages(true));

        // Build a single command to require all enterprise packages
        $composer_require_packages = implode(' ',
            (clone $enterprise_packages)->transform(function (string $package) {
                return "processmaker/$package";
            })->toArray());

        // Convert it to the actual command
        $composer_require_packages = [
            "composer require $composer_require_packages --no-interaction --no-scripts"
        ];

        // Build the stack of commands to run
        $install_and_publish_commands = $enterprise_packages->transform(function (string $package) {
            return [
                PHP_BINARY." artisan $package:install",
                PHP_BINARY." artisan vendor:publish --tag=$package"
            ];
        });

        // Add the composer require command as the first command to run
        $commands = $install_and_publish_commands->prepend($composer_require_packages);

        // Transform each command so it's executed in the proper
        // directory and run it as the appropriate user then flatten
        // the collection and return it as an array
        return $commands->transform(function (array $commands) {
            return array_map(function (string $command) {
                return CommandLineFacade::transformCommandToRunAsUser($command, CODEBASE_PATH);
            }, $commands);
        })->flatten();
    }

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

        return json_decode(FileSystemFacade::get($composer_json_file));
    }
}
