<?php

namespace ProcessMaker\Cli;

use LogicException, RuntimeException;
use Illuminate\Support\Arr;
use \Packages as PackagesFacade;
use Illuminate\Support\Collection;
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

        $install_commands = collect(PackagesFacade::getSupportedPackages(true))
            ->transform(function (string $package) {
                return [
                    "composer require processmaker/$package --no-interaction --no-scripts",
                    PHP_BINARY." artisan $package:install",
                    PHP_BINARY." artisan vendor:publish --tag=$package"
                ];
            })->transform(function (array $commands) {
                return collect($commands)->transform(function (string $command) {
                    return CommandLineFacade::transformCommandToRunAsUser($command, CODEBASE_PATH);
                })->toArray();
            })->flatten();

        $processManager = resolve(ProcessManager::class);

        $processManager->setFinalCallback(function () use ($processManager) {
            $this->outputPostInstallPackages($processManager);
        });

        $processManager->buildProcessesBundleAndStart([$install_commands->toArray()]);
    }

    public function outputPostInstallPackages(ProcessManager $processManager)
    {
        $output = $processManager->getProcessOutput();

        $output->each(function (Collection $output, $command) use ($processManager) {
            if ($output->isNotEmpty()) {
                $exitCode = $processManager->getProcessExitCodeFromOutput($command);

                if ($exitCode === 0) {
                    output("<fg=cyan>Command Success:</>\n$command");
                } else {
                    output("<fg=red>Command Failed:</>\n$command");
                }

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
