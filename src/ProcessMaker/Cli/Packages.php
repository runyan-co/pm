<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use DomainException, Exception, LogicException, RuntimeException;
use ProcessMaker\Cli\Facades\Composer;
use ProcessMaker\Cli\Facades\Config;
use ProcessMaker\Cli\Facades\Git;
use ProcessMaker\Cli\Facades\Core;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Packages
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
     * Non-bpmn and non-executor packages required for core
     * processmaker/processmaker functionality
     *
     * @var array
     */
    public static $otherPackages = ['laravel-i18next'];

    /**
     * Packages required by processmaker/processmaker for core BPMN functioning
     *
     * @var array
     */
    public static $bpmnPackages = ['pmql', 'nayra'];

    /**
     * Complete list of packages for the docker-based script executors
     *
     * @var array
     */
    public static $executorPackages = [
        'docker-executor-csharp',
        'docker-executor-java',
        'docker-executor-lua',
        'docker-executor-php',
        'docker-executor-php-ethos',
        'docker-executor-node',
        'docker-executor-node-ssr',
        'docker-executor-python-selenium',
        'docker-executor-python',
        'docker-executor-r',
    ];

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
     * Get all non-enterprise packages required by processmaker/processmaker
     *
     * @return array
     */
    public static function additionalPackages(): array
    {
        return array_merge(self::$bpmnPackages, self::$executorPackages, self::$otherPackages);
    }

    /**
     * @return mixed
     */
    public function getPackage(string $name): array
    {
        if (Str::contains($name, 'processmaker/')) {
            $name = Str::replace('processmaker/', '', $name);
        }

        if (!array_key_exists($name, $this->getPackages())) {
            throw new LogicException("Package with name \"${name}\" does not exist locally.");
        }

        return $this->getPackages()[$name];
    }

    /**
     * @param  string  $name
     *
     * @return string
     */
    public function getPackagePath(string $name): string
    {
        return $this->getPackage($name)['path'];
    }

    /**
     * @param  bool  $enterpriseOnly
     * @param  string|null  $branch
     *
     * @return array
     */
    public function getSupportedPackages(bool $enterpriseOnly = false, ?string $branch = null): array
    {
        $is41 = ($branch === '4.1-develop');

        if (!$this->packageExists('packages')) {
            $this->clonePackage('packages');
        }

        if (!$is41 && (!Core::isCloned() || !Core::is42())) {
            Core::clone();
        }

        // We need the packages meta-package for version 4.1 to get the list of supported enterprise
        // packages that ProcessMaker 4 is compatible with, otherwise we use the composer.json from
        // the core codebase to retrieve the list of enterprise packages
        if ($is41) {
            $repo = (object) $this->getPackage('packages');
            $path_to_repo = $repo->path;
        } else {
            $path_to_repo = codebase_path();
        }

        // Make sure we're on the right branch
        $branch = $branch ?? Git::getDefaultBranch($path_to_repo);
        $branchSwitchResult = Git::switchBranch($branch, $path_to_repo);

        // Find and decode composer.json
        $composer_json = Composer::getComposerJson($path_to_repo);

        try {
            // We want just the package names for now
            $supported_packages = array_keys(get_object_vars($composer_json->extra->processmaker->enterprise));
        } catch (Exception $exception) {
            throw new LogicException('Enterprise packages not found in composer.json');
        }

        if (! $enterpriseOnly) {
            // Merge the supported enterprise package names with
            // the handful of other packages required for the
            // primary (processmaker/processmaker) app to function
            $supported_packages = array_merge($supported_packages ?? [], self::additionalPackages());
        }

        // Sort it and and remove two packages so they can be
        // prepended as other packages rely on them if the order
        // returned is the order installed
        $supported_packages = collect($supported_packages)->values()->sort()->reject(function ($package) {
            return in_array($package, [
                'docker-executor-node-ssr',
                'connector-send-email',
                'package-collections',
                'package-savedsearch',
                'packages',
            ]);
        });

        // Prepend the removed packages so they're installed
        // first, assuming the returned order is relied on
        // for installation
        return $supported_packages->prepend('package-collections')
                                  ->prepend('package-savedsearch')
                                  ->prepend('connector-send-email')
                                  ->prepend('docker-executor-node-ssr')
                                  ->prepend('packages')
                                  ->toArray();
    }

    /**
     * @param  string  $name
     * @param  bool  $force
     *
     * @return bool
     */
    public function clonePackage(string $name, bool $force = false): bool
    {
        $name = Str::replace('processmaker/', '', $name);

        if (! $force && $this->packageExists($name)) {
            throw new LogicException("Package already exists: processmaker/{$name}");
        }

        if ($force) {
            $this->files->rmdir(Config::packagesPath()."/{$name}");
        }

        $command = "git clone https://github.com/processmaker/{$name}";

        $output = $this->cli->run($command, function ($code, $out) use ($name): void {
            throw new RuntimeException("Failed to clone {$name}: ".PHP_EOL.$out);
        }, Config::packagesPath());

        return $this->packageExists($name);
    }

    /**
     * Clones all supported PM4 packages to the local package directory
     *
     * @param  bool  $force
     *
     * @return void
     */
    public function cloneAllPackages(bool $force = false): void
    {
        // Clear the ProcessMaker\Cli packages directory before
        // we start cloning the new ones down
        if ($force) {
            foreach ($this->getPackages() as $package) {
                $this->files->rmdir($package['path']);
            }
        }

        // Clone down all 4.2 and 4.1 packages
        $packages = array_merge(
            $this->getSupportedPackages(),
            $this->getSupportedPackages(true, '4.1-develop')
        );

        foreach ($packages as $index => $package) {
            try {
                if ($this->clonePackage($package)) {
                    info("Package ${package} cloned successfully!");
                }
            } catch (Exception $exception) {
                warning($exception->getMessage());
            }
        }
    }

    /**
     * @param  string  $name
     *
     * @return bool
     */
    public function packageExists(string $name): bool
    {
        if (Str::contains($name, 'processmaker/')) {
            $name = Str::replace('processmaker/', '', $name);
        }

        return in_array($name, $this->getPackagesListFromDirectory(), true);
    }

    /**
     * Get the names of the local composer packages
     *
     * @param  string|null  $package_directory
     *
     * @return array
     */
    public function getPackagesListFromDirectory(?string $package_directory = null): array
    {
        if (! is_string($package_directory)) {
            $package_directory = packages_path();
        }

        return array_filter($this->files->scandir($package_directory), function ($dir) use ($package_directory) {
            // Set the absolute path to the file or directory
            $dir = $package_directory.'/'.$dir;

            // Filter out any non-directory files
            return $this->files->is_dir($dir) && ! is_file($dir);
        });
    }

    /**
     * Returns an multi-dimensional array with
     * each package name and path
     *
     * @return array
     */
    public function getPackages(): array
    {
        $packages = array_map(static function ($package_name) {
            return [
                'name' => $package_name,
                'path' => packages_path("/${package_name}"),
            ];
        }, $this->getPackagesListFromDirectory());

        return collect($packages)->keyBy('name')->toArray();
    }

    /**
     * Get the package version number for a package
     */
    public function getPackageVersion(string $package_directory): string
    {
        $composer_json = Composer::getComposerJson($package_directory) ?? new class() {};

        if (! property_exists($composer_json, 'version')) {
            return '...';
        }

        return $composer_json->version;
    }

    /**
     * @param  string  $path
     *
     * @return string
     */
    public function getCurrentGitBranchName(string $path): string
    {
        if (! $this->files->is_dir($path)) {
            return '...';
        }
        // Run this command and get the current git branch
        $branch = $this->cli->run('git rev-parse --abbrev-ref HEAD', null, $path);

        // Remove unnecessary end of line character(s)
        return Str::replace(["\n", PHP_EOL], '', $branch);
    }

    /**
     * Stores basic package metadata on this class instance. Used to store metadata
     * prior to running the pull() method for comparisons afterwards.
     *
     * @param  bool  $updated
     * @param  array  $metadata  Any package metadata to start with
     *
     * @return array
     */
    public function takePackagesSnapshot(bool $updated = false, array $metadata = []): array
    {
        foreach ($this->getPackages() as $package) {
            $path = $package['path'];

            $version_key = $updated ? 'updated_version' : 'version';
            $branch_key = $updated ? 'updated_branch' : 'branch';
            $hash_key = $updated ? 'updated_commit_hash' : 'commit_hash';

            $metadata[$package['name']] = [
                'name' => $package['name'],
                $version_key => $this->getPackageVersion($path),
                $branch_key => $this->getCurrentGitBranchName($path),
                $hash_key => Git::getCurrentCommitHash($path),
            ];
        }

        return $metadata;
    }

    /**
     * @param  string|null  $branch
     * @param  array  $commands
     *
     * @return array
     */
    public function buildPullCommands(string $branch = null, array $commands = []): array
    {
        foreach ($this->getPackages() as $package) {
            $package = (object) $package;

            $package_commands = [
                "if [[ -d ./.idea ]]; then mkdir ../{$package->name} && mv .idea ../{$package->name}; fi",
                'git reset --hard',
                'git clean -d -f .',
                'git fetch --all',
                "git checkout ".$branch ?? Git::getDefaultBranch($package->path),
                'git pull --force',
                "if [[ -d ../{$package->name}/.idea ]]; mv ../{$package->name}/.idea . && rm -r ../{$package->name}; fi",
            ];

            $commands[$package->name] = array_map(static function ($command) use ($package) {
                return "cd {$package->path} && ${command}";
            }, $package_commands);
        }

        return $commands;
    }

    /**
     * @return array
     */
    public function getPackagesTableData(): array
    {
        $table = (object) [];

        // Build the table rows by merging the compare-with
        // package metadata with a recent snapshot
        foreach ($this->takePackagesSnapshot() as $package => $updated) {
            $table->$package = (object) $updated;
        }

        // Sort the columns in a more sensible way
        foreach ($table as $key => $row) {
            $table->$key = [
                'name' => "<fg=cyan>{$row->name}</>",
                'version' => $row->version,
                'branch' => $row->branch,
                'commit_hash' => $row->commit_hash,
            ];
        }

        return (array) $table;
    }

    /**
     * Build the stack of commands to composer require and
     * install each enterprise ProcessMaker\Cli 4 package
     */
    public function buildPackageInstallCommands(bool $for_41_develop = false, bool $force = false): Collection
    {
        if (! $this->files->is_dir(codebase_path())) {
            throw new LogicException('Could not find processmaker codebase: '.codebase_path());
        }

        // Find out which branch to switch to in the local
        // processmaker/processmaker codebase
        $branch = $for_41_develop ? '4.1-develop' : 'develop';

        // Find out which branch we're on
        $current_branch = Git::getCurrentBranchName(codebase_path());

        // Make sure we're on the right branch
        if ($current_branch !== $branch && ! $force) {
            throw new DomainException("Core codebase branch should be \"{$branch}\" but \"{$current_branch}\" was found.");
        }

        // Grab the list of supported enterprise packages
        $enterprise_packages = Collection::make(
            $this->getSupportedPackages(true, $branch)
        );

        // Find the composer executable
        $composer = $this->cli->findExecutable('composer');

        // Build the stack of commands to run
        return $enterprise_packages->keyBy(function ($package) {
            return $package;
        })->transform(function (string $package) use ($composer) {

            $artisan_install_command = PHP_BINARY." artisan ${package}:install --no-interaction";

            // Concatenate API keys for packages which require them
            if ($package === 'connector-slack' && ! blank($key = Install::get('slack_api_key'))) {
                $artisan_install_command = "export SLACK_OAUTH_ACCESS_TOKEN={$key} && ".$artisan_install_command;
            }

            if ($package === 'package-googleplaces' && ! blank($key = Install::get('google_places_api_key'))) {
                $artisan_install_command = "export GOOGLE_API_TOKEN={$key} && ".$artisan_install_command;
            }

            return Collection::make([
                "{$composer} require processmaker/{$package} --no-interaction",
                $artisan_install_command,
                PHP_BINARY." artisan vendor:publish --tag={$package} --no-interaction",
            ]);
        })->put('horizon', new Collection([PHP_BINARY.' artisan horizon:terminate']));
    }
}
