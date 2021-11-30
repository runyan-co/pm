<?php

namespace ProcessMaker\Cli;

use Exception;
use LogicException;
use \FileSystem as FileSystemFacade;
use \CommandLine as CommandLineFacade;
use \Packages as PackagesFacade;
use \Composer as ComposerFacade;
use \Git as GitFacade;
use Illuminate\Support\Str;

class Packages
{
    public $cli, $files, $composer;

    /**
     * Where to find all local copies of supported packages
     *
     * @var string
     */
    public $package_directory = USER_HOME.'/packages/composer/processmaker';

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

    /**
     * Get the root directory where all local
     * composer packages are stored
     *
     * @param  string|null  $package_directory
     *
     * @return bool
     */
    public function setPackageDirectory(string $package_directory): bool
    {
        if (FileSystemFacade::isDir($package_directory)) {
            $this->package_directory = $package_directory;
        }

        return FileSystemFacade::isDir($this->package_directory);
    }

    /**
     * @param  string  $name
     *
     * @return mixed
     */
    public function getPackage(string $name)
    {
        if (Str::contains($name, 'processmaker/')) {
            $name = Str::replace('processmaker/', '', $name);
        }

        if (!array_key_exists($name, $this->getPackages())) {
            throw new LogicException("Package with name \"$name\" does not exist locally.");
        }

        return $this->getPackages()[$name];
    }

    public function getSupportedPackages()
    {
        if (!$this->packageExists('packages')) {
            throw new LogicException('"processmaker/packages" composer meta-package not found.');
        }

        // We need the packages meta-package to get the
        // list of supported enterprise packages that
        // ProcessMaker 4 is compatible with
        $packages_package = $this->getPackage('packages');
        $packages_package_path = $packages_package['path'];

        // Make sure we're on the right branch
        $defaultBranch = GitFacade::getDefaultBranch($packages_package_path);
        $branchSwitchResult = GitFacade::switchBranch($defaultBranch, $packages_package_path);

        // Find and decode composer.json
        $composer_json = json_decode(FileSystemFacade::get("$packages_package_path/composer.json"));

        // Get the supported packages
        $supported_packages = [];

        try {
            // We want just the package names for now
            $supported_packages = array_keys(get_object_vars($composer_json->extra->processmaker->enterprise));
        } catch (Exception $exception) {
            return warning('Enterprise packages not found in processmaker/packages composer.json');
        }

        // Merge the supported enterprise package names with
        // the handful of other packages required for the
        // primary (processmaker/processmaker) app to function
        $supported_packages = array_merge($supported_packages, self::$additionalPackages);

        // Sort it and send it back
        return collect($supported_packages)->sort()->toArray();
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

        if ($this->packageExists($name) && !$force) {
            throw new LogicException("Package already exists: processmaker/$name");
        }

        if ($force) {
            FileSystemFacade::rmdir($this->package_directory."/$name");
        }

        $command = "git clone https://github.com/ProcessMaker/$name";

        CommandLineFacade::runAsUser($command, function ($code, $out) use ($name) {
            warning("Failed to clone $name:"); output($out);
        }, $this->package_directory);

        return $this->packageExists($name);
    }

    public function cloneAllPackages()
    {
        // Clear the ProcessMaker packages directory before
        // we start cloning the new ones down
        foreach ($this->getPackages() as $package) {
            FileSystemFacade::rmdir($package['path']);
        }

        // Clone down the processmaker/packages meta-package to
        // to make sure we can reference all official supported
        // ProcessMaker 4 enterprise packages
        if (!$this->packageExists('packages')) {
            $this->clonePackage('packages');
        }

        dump($this->getSupportedPackages());

        return true;
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

        return in_array($name, $this->getPackagesListFromDirectory());
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
        if (!is_string($package_directory)) {
            $package_directory = $this->package_directory;
        }

        return array_filter(FileSystemFacade::scandir($package_directory), function ($dir) {

            // Set the absolute path to the file or directory
            $dir = $this->package_directory.'/'.$dir;

            // Filter out any non-directory files
            return FileSystemFacade::isDir($dir) && !is_file($dir);
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
                'path' => "$this->package_directory/$package_name"
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
        $composer_json = ComposerFacade::getComposerJson($package_directory) ?? new class {};

        if (!property_exists($composer_json, 'version')) {
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
        if (!FileSystemFacade::isDir($path)) {
            return '...';
        }

        // Run this command and get the current git branch
        $branch = CommandLineFacade::runAsUser('git rev-parse --abbrev-ref HEAD', null, $path);

        // Remove unnecessary end of line character(s)
        return Str::replace(["\n", PHP_EOL], "", $branch);
    }

    /**
     * @param  bool  $verbose
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function pull41(bool $verbose = false)
    {
        $this->pull($verbose, '4.1-develop');
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
        foreach (PackagesFacade::getPackages() as $package) {

            $version_key = $updated ? 'updated_version' : 'version';
            $branch_key = $updated ? 'updated_branch' : 'branch';

            $metadata[$package['name']] = [
                'name' => $package['name'],
                $version_key => PackagesFacade::getPackageVersion($package['path']),
                $branch_key => PackagesFacade::getCurrentGitBranchName($package['path'])
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
        foreach (PackagesFacade::getPackages() as $package) {
            $package_commands = [
                'git reset --hard',
                'git clean -d -f .',
                "git checkout $branch",
                'git fetch --all',
                'git pull --force',
            ];

            $commands[$package['name']] = array_map(function ($command) use ($package) {
                return 'cd '.$package['path'].' && sudo -u '.user().' '.$command;
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
        $commands = PackagesFacade::buildPullCommands($branch ?? $get_default_git_branch);

        // Store the pre-pull metadata for each package
        $metadata = PackagesFacade::takePackagesSnapshot();

        // Create a new ProcessManagerFacade instance to run the
        // git commands in parallel where possible
        $processManager = resolve(ProcessManager::class);

        // Set verbosity for output to stdout
        $processManager->setVerbosity($verbose);

        // Set a closure to be called when the final process exits
        $processManager->setFinalCallback(function () use ($metadata) {
            PackagesFacade::outputPullResults($metadata);
        });

        // Build the process queue and run
        $processManager->buildProcessesBundleAndStart($commands);
    }

    /**
     * @param  array  $metadata
     */
    public function outputPullResults(array $metadata)
    {
        $table = [];

        // Build the table rows
        foreach (PackagesFacade::takePackagesSnapshot(true) as $package => $updated) {
            $table[$package] = array_merge($metadata[$package], $updated);
        }

        // Sort the columns in a more sensible way
        foreach ($table as $key => $row) {
            $table[$key] = [
                'name' => $row['name'],
                'version' => $row['version'],
                'updated_version' => $row['updated_version'],
                'branch' => $row['branch'],
                'updated_branch' => $row['updated_branch']
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
            // have switch to 4.1 or 4.1 during the pull, which
            // is set by the user by adding a flag to the command
            if ($row['branch'] !== $row['updated_branch']) {
                $table[$key]['updated_branch'] = '<info>'.$row['updated_branch'].'</info>';
            }
        }

        // Add a new line for space above the table
        output(PHP_EOL);

        // Format our results in an easy-to-ready table
        table(['Name', 'Version', 'Updated Version', 'Branch', 'Updated Branch'], $table);
    }
}
