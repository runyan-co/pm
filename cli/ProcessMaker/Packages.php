<?php

namespace ProcessMaker\Cli;

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
     * Returns the name and absolute path to the local composer package directory
     *
     * @param  string  $name
     *
     * @return array
     */
    public function findComposerPackageLocally(string $name): array
    {
        if (Str::contains($name, 'processmaker/')) {
            $name = Str::replace('processmaker/', '', $name);
        }

        return collect($this->getPackages())->keyBy('name')->get($name) ?? [];
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
     * @param  bool  $for_41_develop
     * @param  bool  $verbose
     */
    public function pull(bool $for_41_develop = false, bool $verbose = false)
    {
        $git_branch = $for_41_develop
            ? '4.1-develop'
            : "$(git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@')";

        $package_commands = [
            'git reset --hard',
            'git clean -d  -f .',
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
            $package_set['branch'] = Packages::getCurrentGitBranchName($package['path']);

            $package_set['commands'] = array_map(function ($command) use ($package) {
                return 'cd '.$package['path'].' && sudo -u '.user().' '.$command;
            }, $package_commands);
        }

        $commands = collect($result)->transform(function (array $set) {
            return $set['commands'];
        })->toArray();

        // Create a new ProcessManager instance to run the
        // git commands in parallel where possible
        $processManager = new ProcessManager(new CommandLine);

        // Set verbosity for output to stdout
        $processManager->setVerbosity($verbose);

        // Set a closure to be called when the final process exits
        $processManager->setFinalCallback(function () use ($result) {

            $final_result = [];

            foreach ($this->getPackages() ?? [] as $package) {

                $package_set = $result[$package['name']];
                $final_result[$package['name']] = [];
                $symbol = &$final_result[$package['name']];

                $symbol['name'] = '<fg=cyan>'.$package['name'].'</>';
                $symbol['version'] = $package_set['version'];
                $symbol['updated_version'] = Packages::getPackageVersion($package['path']);

                if ($symbol['updated_version'] !== $symbol['version']) {
                    $symbol['updated_version'] = '<info>'.$symbol['updated_version'].'</info>';
                }

                $symbol['branch'] = $package_set['branch'];
                $symbol['updated_branch'] = Packages::getCurrentGitBranchName($package['path']);

                if ($symbol['updated_branch'] !== $symbol['branch']) {
                    $symbol['updated_branch'] = '<info>'.$symbol['updated_branch'].'</info>';
                }
            }

            $table = collect($final_result)->sortBy('name')->values()->toArray();

            table(['Name', 'Version', 'Updated Version', 'Branch', 'Updated Branch'], $table);
        });

        $processManager->buildProcessesBundleAndStart($commands);
    }
}
