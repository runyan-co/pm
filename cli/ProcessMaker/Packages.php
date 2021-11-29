<?php

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;

class Packages
{
    public $cli;

    public $files;

    public $package_directory;

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

        return $this->files->scandir($package_directory);
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
            return warning("Path not found: $path_to_composer_json");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "$path_to_composer_json/composer.json";

        if (!$this->files->exists($composer_json_file)) {
            return warning("Composer.json not found: $composer_json_file");
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
     * Get the default branch name for the Git repo in
     * the current PHP working directory
     *
     * @param  bool  $for_41_develop
     *
     * @return string
     */
    public function getDefaultGitBranch(bool $for_41_develop = false): string
    {
        $git_branch = ! $for_41_develop
            ? $this->cli->runAsUser("git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'")
            : '4.1-develop';

        if (!is_string($git_branch)) {
            return '';
        }

        // Remove unnecessary end of line character(s)
        return Str::replace(["\n", PHP_EOL], "", $git_branch);
    }

    /**
     * @param  string  $path
     *
     * @return string
     */
    public function getCurrentGitBranchName(string $path): string
    {
        $branch = $this->cli->runAsUser("cd $path && echo $(git rev-parse --abbrev-ref HEAD)");

        // Remove unnecessary end of line character(s)
        return Str::replace(["\n", PHP_EOL], "", $branch);
    }

    public function pull(bool $for_41_develop = false, bool $verbose = false)
    {
        $git_branch = $for_41_develop
            ? '4.1-develop'
            : "$(git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@')";

        $package_commands = [
            'git reset --hard',
            "git checkout $git_branch",
            'git fetch --all',
            'git pull --force',
        ];

        $result = [];

        $packages = $this->getPackages();

        foreach ($packages ?? [] as $package) {

            if (!array_key_exists($package['name'], $result)) {
                $result[$package['name']] = [];
            }

            $package_set = &$result[$package['name']];

            $package_set['version'] = Packages::getPackageVersion($package['path']);

            $package_set['path'] = $package['path'];

            $package_set['commands'] = array_map(function ($command) use ($package) {
                return 'sudo -u '.user().' cd '.$package['path'].' && '.$command;
            }, $package_commands);
        }

        $commands = collect($result)->transform(function (array $set) {
            return $set['commands'];
        })->toArray();

        // Create a new ProcessManager instance to run the
        // git commands in parallel where possible
        $processManager = new ProcessManager($this->cli);

        $processManager->setFinalCallback(function () use (&$result) {
            foreach ($this->getPackages() ?? [] as $package) {

                $package_set = &$result[$package['name']];
                $package_set['updated_version'] = $this->getPackageVersion($package['path']);
                $package_set['branch'] = $this->getCurrentGitBranchName($package['path']);

                unset($package_set['path']);
                unset($package_set['commands']);
            }

            dump($result);
        });

        $processManager->setVerbosity($verbose);
        $processManager->buildProcessesBundleAndStart($commands);
    }

    /**
     * Iterate through each local composer package and
     * pull down the latest from GitHub
     *
     * @param  bool  $for_41_develop
     * @param  string|null  $directory
     *
     * @return array
     */
    public function pullPackages(bool $for_41_develop = false, string $directory = null): array
    {
        $results = [];

        if (is_string($directory)) {
            if (!$this->setPackageDirectory($directory)) {
                warning("Could not set packages directory: $directory");

                return $results;
            }
        }

        $packages = $this->getPackages();

        // Commands to run in the package's directory
        $commands = [
            'git reset --hard',
            'git checkout {git_branch}',
            'git fetch --all',
            'git pull --force',
        ];

        // Total number of commands to be run in sum
        $commands_count = count($packages) * count($commands);
        $current_count = 0;

        // Progress bar makes it easier to keep track of
        $this->cli->getProgress($commands_count)->start();

        // Keep track of exceptions/errors when running commands
        $errors = [];

        // Iterate through and run necessary commands to pull down
        // the latest and switch to the default branch for each
        foreach($packages as $package) {

            $package_directory = $package['path'];
            $package = $package['name'];
            $errors[$package] = [];

            if (!is_dir($package_directory)) {
                warning("Skipping since the package directory wasn't found: $package_directory");

                continue;
            }

            if (!chdir($package_directory)) {
                warning("Could not change to directory: $package_directory");

                continue;
            }

            // Delete node_modules/ and vendor/ if they exist
            if (is_dir('node_modules')) {
                $this->files->rmdir('node_modules');
            }

            if (is_dir('vendor')) {
                $this->files->rmdir('vendor');
            }

            // Pre-update package version
            $current_version = $this->getPackageVersion($package_directory);

            foreach ($commands as $command) {

                // Current command number we're on
                ++$current_count;

                // Replace the string 'git_branch' with the actual
                // default git_branch for this package
                if (Str::contains($command, '{git_branch}')) {
                    $command = Str::replace('{git_branch}', $this->getDefaultGitBranch($for_41_develop), $command);
                }

                $output = $this->cli->runAsUser($command, function ($message) use (&$errors, $package) {
                    array_push($errors[$package], $message);
                });

                output($output);

                $this->cli->getProgress()->advance();
            }

            $updated_to_version = $this->getPackageVersion($package_directory);

            if ($current_version !== $updated_to_version) {
                $updated_to_version = "<info>$updated_to_version</info>";
            }

            $error_count = count($errors[$package]);

            $results[] = [
                "processmaker/$package",
                $current_version,
                $updated_to_version,
                $for_41_develop ? 'Yes' : 'No',
                $error_count === 0 ? "<info>0</info>" : "<fg=red>$error_count</>"
            ];
        }

        $this->cli->getProgress()->finish();

        // Needed to separate progress bar output
        output(PHP_EOL);

        return $results;
    }

    /**
     * @param  string  $command
     * @param  bool  $for_41_develop
     *
     * @return string
     */
    public function setGitBranchInCommandString(string $command, bool $for_41_develop = false): string
    {
        if (!Str::contains($command, '{git_branch}')) {
            return $command;
        }

        $git_branch = ! $for_41_develop
            ? $this->cli->runAsUser("git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'")
            : '4.1-develop';

        if (!is_string($git_branch)) {
            return '';
        }

        // Remove unnecessary end of line character(s)
        $git_branch = Str::replace(["\n", PHP_EOL], "", $git_branch);

        // Replace the {git_branch} variable with the actual branch name
        return Str::replace('{git_branch}', $git_branch, $command);
    }
}
