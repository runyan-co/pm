<?php

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class Packages
{
    public $cli;

    public $files;

    public $package_directory;

    public $progress_bar;

    public function __construct()
    {
        $this->cli = new CommandLine();
        $this->files = new FileSystem();
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
        if (is_dir($package_directory)) {
            $this->package_directory = $package_directory;
        }

        return is_dir($this->package_directory);
    }

    /**
     * Get the names of the local composer packages
     *
     * @param  string|null  $package_directory
     *
     * @return array|false
     */
    public function getPackageList(string $package_directory = null)
    {
        if (!is_string($package_directory)) {
            $package_directory = $this->package_directory;
        }

        // Filter out the "." and ".." directories
        return array_filter(scandir($package_directory), function ($package_directory) {
            return ! Str::contains($package_directory, ['.', '..']);
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
     * @param  string  $absolutePath
     *
     * @return bool
     */
    public function removeDirectory(string $absolutePath): bool
    {
        if (!is_dir($absolutePath)) {
            warning("Could not remove directory (it does not exist): $absolutePath");
            return false;
        }

        info("Removing directory: $absolutePath");

        $this->cli->runAsUser("rm -rf $absolutePath", function ($error) use ($absolutePath) {
            warning("Error attempting to remove $absolutePath");
        });

        return true;
    }

    /**
     * @param  string  $path_to_composer_json
     *
     * @return void|object
     */
    public function getComposerJson(string $path_to_composer_json)
    {
        if (!is_dir($path_to_composer_json)) {
            return warning("Path not found: $path_to_composer_json");
        }

        if (Str::endsWith($path_to_composer_json, '/')) {
            $path_to_composer_json = Str::replaceLast('/', '', $path_to_composer_json);
        }

        $composer_json_file = "$path_to_composer_json/composer.json";

        if (!is_file($composer_json_file)) {
            return warning("Composer.json not found: $composer_json_file");
        }

        return json_decode(file_get_contents($composer_json_file));
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

        if (!property_exists($composer_json, 'version')) {
            return '...';
        }

        return $composer_json->version;
    }

    /**
     * @param  int|null  $count
     *
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    public function getProgressBar(int $count = null): ProgressBar
    {
        if (!$this->progress_bar instanceof ProgressBar) {
            $this->progress_bar = new ProgressBar(new ConsoleOutput(), $count);
            $this->progress_bar->setRedrawFrequency(25);
            $this->progress_bar->minSecondsBetweenRedraws(0.025);
            $this->progress_bar->maxSecondsBetweenRedraws(0.05);
        }

        return $this->progress_bar;
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
        $this->getProgressBar($commands_count)->start();

        // Iterate through and run necessary commands to pull down
        // the latest and switch to the default branch for each
        foreach($packages as $package) {

            $package_directory = $package['path'];
            $package = $package['name'];

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
                $this->removeDirectory('node_modules');
            }

            if (is_dir('vendor')) {
                $this->removeDirectory('vendor');
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

                $this->cli->runAsUser($command);
            }

            $updated_to_version = $this->getPackageVersion($package_directory);

            if ($current_version !== $updated_to_version) {
                $updated_to_version = "<info>$updated_to_version</info>";
            }

            $results[] = ["processmaker/$package", $current_version, $updated_to_version, $for_41_develop ? 'Yes' : 'No'];
        }

        $this->getProgressBar()->finish();

        // Needed to separate progress bar output
        output(PHP_EOL);

        return $results;
    }
}
