<?php

namespace ProcessMaker\Cli;

use Closure;
use RuntimeException;
use Illuminate\Support\Str;

class Packages
{
    public $cli, $files, $package_directory;

    public function __construct(CommandLine $cli, FileSystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
        $this->package_directory = getenv('HOME').'/packages/composer/processmaker';
    }

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
        if ($this->files->isDir($package_directory)) {
            $this->package_directory = $package_directory;
        }

        return $this->files->isDir($this->package_directory);
    }

    /**
     * Get the names of the local composer packages
     *
     * @param  string|null  $package_directory
     *
     * @return array
     */
    public function getPackageList(string $package_directory = null): array
    {
        if (!is_string($package_directory)) {
            $package_directory = $this->package_directory;
        }

        return array_filter($this->files->scandir($package_directory), function ($dir) {

            // Set the absolute path to the file or directory
            $dir = $this->package_directory.'/'.$dir;

            // Filter out any non-directory files
            return $this->files->isDir($dir) && !is_file($dir);
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
        return array_map(function ($package_name) {
            return [
                'name' => $package_name,
                'path' => "$this->package_directory/$package_name"
            ];
        }, $this->getPackageList());
    }

    /**
     * @param  string  $path_to_composer_json
     *
     * @return void|object
     */
    public function getComposerJson(string $path_to_composer_json)
    {
        if (!$this->files->isDir($path_to_composer_json)) {
            throw new RuntimeException("Path to composer.json not found: $path_to_composer_json");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "$path_to_composer_json/composer.json";

        if (!$this->files->exists($composer_json_file)) {
            throw new RuntimeException("Composer.json not found: $composer_json_file");
        }

        return json_decode($this->files->get($composer_json_file));
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
        $composer_json = $this->getComposerJson($package_directory);

        if (!property_exists($composer_json ?? new class {}, 'version')) {
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
        if (!$this->files->isDir($path)) {
            return '...';
        }

        // Run this command and get the current git branch
        $branch = $this->cli->runAsUser('git rev-parse --abbrev-ref HEAD', null, $path);

        // Remove unnecessary end of line character(s)
        return Str::replace(["\n", PHP_EOL], "", $branch);
    }

    /**
     * @param  bool  $verbose
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
        foreach (Packages::getPackages() as $package) {

            $version_key = $updated ? 'updated_version' : 'version';
            $branch_key = $updated ? 'updated_branch' : 'branch';

            $metadata[$package['name']] = [
                'name' => $package['name'],
                $version_key => Packages::getPackageVersion($package['path']),
                $branch_key => Packages::getCurrentGitBranchName($package['path'])
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
        foreach (Packages::getPackages() as $package) {
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
     */
    public function pull(bool $verbose = false, string $branch = null): void
    {
        // A quick command (thanks Nolan!) to grab the default branch
        $get_default_git_branch = "$(git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@')";

        // Build the commands for each package (keyed by package name)
        $commands = Packages::buildPullCommands($branch ?? $get_default_git_branch);

        // Store the pre-pull metadata for each package
        $metadata = Packages::takePackagesSnapshot();

        // Create a new ProcessManager instance to run the
        // git commands in parallel where possible
        $processManager = new ProcessManager($this->cli);

        // Set verbosity for output to stdout
        $processManager->setVerbosity($verbose);

        // Set a closure to be called when the final process exits
        $processManager->setFinalCallback(function () use ($metadata) {
            Packages::outputPullResults($metadata);
        });

        // Build the process queue and run
        $processManager->buildProcessesBundleAndStart($commands);
    }

    public function outputPullResults(array $metadata)
    {
        $table = [];

        // Build the table rows
        foreach (Packages::takePackagesSnapshot(true) as $package => $updated) {
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
