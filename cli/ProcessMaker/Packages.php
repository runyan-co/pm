<?php

namespace ProcessMaker\Cli;

use Exception;
use LogicException;
use DomainException;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use ProcessMaker\Facades\Git;
use ProcessMaker\Facades\Composer;
use ProcessMaker\Facades\Config;

class Packages
{
    public CommandLine $cli;

    public FileSystem $files;

    /**
     * Packages that are required by processmaker/processmaker,
     * but aren't included in the list of enterprise packages.
     *
     * @var string[]
     */
    protected static $additionalPackages = [
        'pmql',
        'nayra',
        'docker-executor-lua',
        'docker-executor-php',
        'docker-executor-node',
    ];

    public function __construct(CommandLine $cli, FileSystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * @param  string  $name
     *
     * @return mixed
     */
    public function getPackage(string $name): array
    {
        if (Str::contains($name, 'processmaker/')) {
            $name = Str::replace('processmaker/', '', $name);
        }

        if (! array_key_exists($name, $this->getPackages())) {
            throw new LogicException("Package with name \"$name\" does not exist locally.");
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
    public function getSupportedPackages(bool $enterpriseOnly = false, string $branch = null): array
    {
        if (! $this->packageExists('packages')) {
            $this->clonePackage('packages');
        }

        // We need the packages meta-package to get the
        // list of supported enterprise packages that
        // ProcessMaker 4 is compatible with
        $packages_package = $this->getPackage('packages');
        $packages_package_path = $packages_package['path'];

        // Make sure we're on the right branch
        $branch = $branch ?? Git::getDefaultBranch($packages_package_path);
        $branchSwitchResult = Git::switchBranch($branch, $packages_package_path);

        // Find and decode composer.json
        $composer_json = Composer::getComposerJson($packages_package_path);

        try {
            // We want just the package names for now
            $supported_packages = array_keys(get_object_vars($composer_json->extra->processmaker->enterprise));
        } catch (Exception $exception) {
            throw new LogicException('Enterprise packages not found in processmaker/packages composer.json');
        }

        if (! $enterpriseOnly) {
            // Merge the supported enterprise package names with
            // the handful of other packages required for the
            // primary (processmaker/processmaker) app to function
            $supported_packages = array_merge($supported_packages ?? [], self::$additionalPackages);
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
            throw new LogicException("Package already exists: processmaker/$name");
        }

        if ($force) {
            $this->files->rmdir(Config::packagesPath()."/$name");
        }

        $command = "git clone https://github.com/processmaker/$name";

        $output = $this->cli->run($command, function ($code, $out) use ($name) {
            throw new RuntimeException("Failed to clone $name: ".PHP_EOL.$out);
        }, Config::packagesPath());

        return $this->packageExists($name);
    }

    /**
     * Clones all supported PM4 packages to the local package directory
     *
     * @param  bool  $force
     *
     * @return array
     */
    public function cloneAllPackages(bool $force = false): array
    {
        // Clear the ProcessMaker packages directory before
        // we start cloning the new ones down
        if ($force) {
            foreach ($this->getPackages() as $package) {
                $this->files->rmdir($package['path']);
            }
        }
        // Clone down the processmaker/packages meta-package to
        // to make sure we can reference all official supported
        // ProcessMaker 4 enterprise packages
        if (! $this->packageExists('packages')) {
            $this->clonePackage('packages');
        }

        return $this->getSupportedPackages();
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
    public function getPackagesListFromDirectory(string $package_directory = null): array
    {
        if (! is_string($package_directory)) {
            $package_directory = Config::packagesPath();
        }

        return array_filter($this->files->scandir($package_directory), function ($dir) use ($package_directory) {
            // Set the absolute path to the file or directory
            $dir = $package_directory.'/'.$dir;

            // Filter out any non-directory files
            return $this->files->isDir($dir) && ! is_file($dir);
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
        $packages = array_map(function ($package_name) {
            return [
                'name' => $package_name,
                'path' => Config::packagesPath()."/$package_name",
            ];
        }, $this->getPackagesListFromDirectory());

        return collect($packages)->keyBy('name')->toArray();
    }

    /**
     * Get the package version number for a package
     *
     * @param  string  $package_directory
     *
     * @return string
     */
    public function getPackageVersion(string $package_directory): string
    {
        $composer_json = Composer::getComposerJson($package_directory) ?? new class {};

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
        if (! $this->files->isDir($path)) {
            return '...';
        }
        // Run this command and get the current git branch
        $branch = $this->cli->run('git rev-parse --abbrev-ref HEAD', null, $path);

        // Remove unnecessary end of line character(s)
        return Str::replace(["\n", PHP_EOL], "", $branch);
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

            $version_key = $updated
                ? 'updated_version'
                : 'version';

            $branch_key = $updated
                ? 'updated_branch'
                : 'branch';

            $hash_key = $updated
                ? 'updated_commit_hash'
                : 'commit_hash';

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
     * @param  string  $branch
     * @param  array  $commands
     *
     * @return array
     */
    public function buildPullCommands(string $branch, array $commands = []): array
    {
        foreach ($this->getPackages() as $package) {
            $name = $package['name'];
            $path = $package['path'];

            $package_commands = [
                "if [[ -d ./.idea ]]; then mkdir ../$name && mv .idea ../$name; fi",
                'git reset --hard',
                'git clean -d -f .',
                'git fetch --all',
                "git checkout $branch",
                'git pull --force',
                "if [[ -d ../$name/.idea ]]; mv ../$name/.idea . && rm -r ../$name; fi"
            ];

            $commands[$name] = array_map(static function ($command) use ($path) {
                return "cd $path && $command";
            }, $package_commands);
        }

        return $commands;
    }

    /**
     * @param  bool  $verbose
     * @param  string|null  $branch
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function pull(bool $verbose = false, string $branch = null): void
    {
        // A quick command (thanks Nolan!) to grab the default branch
        $get_default_git_branch = "$(git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@')";

        // Build the commands for each package (keyed by package name)
        $commands = $this->buildPullCommands($branch ?? $get_default_git_branch);

        // Store the pre-pull metadata for each package
        $metadata = $this->takePackagesSnapshot();

        // Create a new ProcessManagerFacade instance to run the
        // git commands in parallel where possible
        $processManager = resolve(ProcessManager::class);

        // Set verbosity for output to stdout
        $processManager->setVerbosity($verbose);

        // Set a closure to be called when the final process exits
        $processManager->setFinalCallback(function () use ($metadata) {
            $this->outputPullResults($metadata);
        });

        // Build the process queue and run
        $processManager->buildProcessesBundleAndStart($commands);
    }

    /**
     * @return array
     */
    public function getPackagesTableData(): array
    {
        $table = [];

        // Build the table rows by merging the compare-with
        // package metadata with a recent snapshot
        foreach ($this->takePackagesSnapshot() as $package => $updated) {
            $table[$package] = $updated;
        }

        // Sort the columns in a more sensible way
        foreach ($table as $key => $row) {
            $table[$key] = [
                'name' => '<fg=cyan>'.$row['name'].'</>',
                'version' => $row['version'],
                'branch' => $row['branch'],
                'commit_hash' => $row['commit_hash'],
            ];
        }

        return $table;
    }

    /**
     * @param  array  $pre_pull_package_metadata
     */
    public function outputPullResults(array $pre_pull_package_metadata)
    {
        $table = [];

        // Build the table rows
        foreach ($this->takePackagesSnapshot(true) as $package => $updated) {
            $table[$package] = array_merge($pre_pull_package_metadata[$package], $updated);
        }

        // Sort the columns in a more sensible way
        foreach ($table as $key => $row) {
            $table[$key] = [
                'name' => $row['name'],
                'version' => $row['version'],
                'updated_version' => $row['updated_version'],
                'branch' => $row['branch'],
                'updated_branch' => $row['updated_branch'],
                'commit_hash' => $row['commit_hash'],
                'updated_commit_hash' => $row['updated_commit_hash'],
            ];
        }

        // Add console styling
        foreach ($table as $key => $row) {
            // Highlight the package name
            $table[$key]['name'] = '<fg=cyan>'.$row['name'].'</>';

            // If the versions are the same, no updated occurred.
            // If they are different, let's make it easier to see.
            if ($row['version'] !== $row['updated_version']) {
                $table[$key]['updated_version'] = '<info>'.$row['updated_version'].'</info>';
            }

            // Do the same thing with branches, since we may
            // have switch to 4.1 or 4.2 during the pull, which
            // is set by the user by adding a flag to the command
            if ($row['branch'] !== $row['updated_branch']) {
                $table[$key]['updated_branch'] = '<info>'.$row['updated_branch'].'</info>';
            }

            // One more time to see if the commit hash has changed
            if ($row['commit_hash'] !== $row['updated_commit_hash']) {
                $table[$key]['updated_commit_hash'] = '<info>'.$row['updated_commit_hash'].'</info>';
            }
        }

        // Add a new line for space above the table
        output(PHP_EOL);

        // Format our results in an easy-to-ready table
        table(['Name', 'Version ->', '-> Version', 'Branch ->', '-> Branch', 'Hash ->', '-> Hash'], $table);
    }

    /**
     * Build the stack of commands to composer require and
     * install each enterprise ProcessMaker 4 package
     *
     * @param  bool  $for_41_develop
     * @param  bool  $force
     *
     * @return \Illuminate\Support\Collection
     */
    public function buildPackageInstallCommands(bool $for_41_develop = false, bool $force = false): Collection
    {
        if (! $this->files->isDir(Config::codebasePath())) {
            throw new LogicException('Could not find ProcessMaker codebase: '.Config::codebasePath());
        }

        // Find out which branch to switch to in the local
        // processmaker/processmaker codebase
        $branch = $for_41_develop
            ? '4.1-develop'
            : 'develop';

        // Find out which branch we're on
        $current_branch = Git::getCurrentBranchName(Config::codebasePath());

        // Make sure we're on the right branch
        if ($current_branch !== $branch && ! $force) {
            throw new DomainException("Core codebase branch should be \"$branch\" but \"$current_branch\" was found.");
        }

        // Grab the list of supported enterprise packages
        $enterprise_packages = new Collection($this->getSupportedPackages(true, $branch));

        // Build the stack of commands to run
        return $enterprise_packages->keyBy(fn($package) => $package)->transform(function (string $package) {
            return new Collection([
                COMPOSER_BINARY." require processmaker/$package --no-interaction",
                PHP_BINARY." artisan $package:install --no-interaction",
                PHP_BINARY." artisan vendor:publish --tag=$package --no-interaction",
            ]);
        })->put('horizon', new Collection([PHP_BINARY.' artisan horizon:terminate']));
    }
}
