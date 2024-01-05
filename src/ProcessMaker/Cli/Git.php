<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use LogicException, RuntimeException;
use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\CommandLine as Cli;
use ProcessMaker\Cli\Facades\FileSystem;

class Git
{
    /**
     * @param  string  $path
     *
     * @return void
     */
    public function validateGitRepository(string $path): void
    {
        if (! FileSystem::is_dir($path)) {
            throw new LogicException("Directory to git repository does not exist: {$path}");
        }

        if (! FileSystem::exists("{$path}/.git")) {
            throw new LogicException("Git repository not found in directory: {$path}");
        }
    }

    /**
     * Retrieve the git repo's current branch name
     */
    public function getCurrentBranchName(string $path_to_repo): string
    {
        $this->validateGitRepository($path_to_repo);

        $output = Cli::run('git rev-parse --abbrev-ref HEAD', function ($e, $o): void {
            throw new RuntimeException('Error trying to retrieve current git branch name');
        }, $path_to_repo);

        return Str::replace([PHP_EOL, "\n"], '', $output);
    }

    /**
     * @param  string  $path_to_repo
     *
     * @return string
     */
    public function getCurrentCommitHash(string $path_to_repo): string
    {
        $this->validateGitRepository($path_to_repo);

        $output = Cli::run('git rev-parse --short HEAD', function ($e, $o): void {
            throw new RuntimeException('Error trying to retrieve current git commit hash.');
        }, $path_to_repo);

        return Str::replace([PHP_EOL, "\n"], '', $output);
    }

    /**
     * @param  string  $path
     *
     * @return string
     */
    public function getDefaultBranch(string $path): string
    {
        $this->validateGitRepository($path);

        $command = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";

        $branch = Cli::run($command, function ($errorCode, $output): void {
            output("<warning>Could not find default git branch.</warning>".PHP_EOL.$output);
        }, $path);

        return Str::replace([PHP_EOL, "\n"], '', $branch);
    }

    /**
     * @param  string  $branchName
     * @param  string  $path
     * @param  bool  $force
     *
     * @return string
     */
    public function switchBranch(string $branchName, string $path, bool $force = false): string
    {
        $this->validateGitRepository($path);

        if ($force) {
            Cli::run('git restore .', null, $path);
            Cli::run('git clean -d -f .', null, $path);
        }

        $switched = Cli::run("git fetch origin {$branchName} && git checkout {$branchName}", function ($exit, $output): void {
            throw new RuntimeException($output);
        }, $path);

        return Str::replace([PHP_EOL, "\n"], '', $switched);
    }

    /**
     * Clone a processmaker package using git
     *
     * @param  string  $package
     * @param  string  $path
     * @param  string|null  $name
     *
     * @return void
     */
    public function clone(string $package, string $path, string $name = null): void
    {
        $cmd = static function (string $repository) use ($name) {
            $command = is_string($token = getenv('GITHUB_TOKEN'))
                ? "git clone https://{$token}@github.com/ProcessMaker/{$repository}"
                : "git clone https://github.com/ProcessMaker/{$repository}";

            return $name ? "{$command} {$name}" : $command;
        };

        Cli::run($cmd($package), function ($code, $output): void {
            throw new RuntimeException($output);
        }, $path);
    }
}
