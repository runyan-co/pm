<?php

namespace ProcessMaker\Cli;

use LogicException;
use RuntimeException;
use \CommandLine as Cli;
use \FileSystem as Fs;
use Illuminate\Support\Str;

class Git
{
    public function validateGitRepository(string $path): void
    {
        if (!Fs::isDir($path)) {
            throw new LogicException("Directory to git repository does not exist: $path");
        }

        if (!Fs::exists("$path/.git")) {
            throw new LogicException("Git repository not found in directory: $path");
        }
    }

    /**
     * Retrieve the git repo's current branch name
     *
     * @param  string  $path_to_repo
     *
     * @return string
     */
    public function getCurrentBranchName(string $path_to_repo): string
    {
        $this->validateGitRepository($path_to_repo);

        $output = Cli::run('git rev-parse --abbrev-ref HEAD', function ($e, $o) {
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

        $output = Cli::run('git rev-parse --short HEAD', function ($e, $o) {
            throw new RuntimeException('Error trying to retrieve current git commit hash.');
        }, $path_to_repo);

        return Str::replace([PHP_EOL, "\n"], '', $output);
    }

    public function getDefaultBranch(string $path): string
    {
        $this->validateGitRepository($path);

        $command = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";

        $branch = Cli::run($command, function ($e, $o) {
            warning('Could not find default git branch.');
            output($o);
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
            Cli::run('git reset --hard', null, $path);
            Cli::run('git clean -d -f .', null, $path);
        }

        $switched = Cli::run("git checkout $branchName", function ($e, $o) {
            throw new RuntimeException('Failed to switch branch');
        }, $path);

        return Str::replace([PHP_EOL, "\n"], '', $switched);
    }

    public function clone(string $package, string $path)
    {
        $token = getenv('GITHUB_TOKEN');
        $cmd = "git clone https://${token}@github.com/processmaker/${package}";

        Cli::runCommand($cmd, function($code, $output) {
            throw new RuntimeException($output);
        }, $path);
    }
}
