<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

class Git
{
    public CommandLine $cli;

    public FileSystem $files;

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
     * @param  string  $path
     *
     * @return void
     */
    public function validateGitRepository(string $path): void
    {
        if (! $this->files->isDir($path)) {
            throw new LogicException("Directory to git repository does not exist: ${path}");
        }

        if (! $this->files->exists("${path}/.git")) {
            throw new LogicException("Git repository not found in directory: ${path}");
        }
    }

    /**
     * Retrieve the git repo's current branch name
     */
    public function getCurrentBranchName(string $path_to_repo): string
    {
        $this->validateGitRepository($path_to_repo);

        $output = $this->cli->run('git rev-parse --abbrev-ref HEAD', function ($e, $o): void {
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

        $output = $this->cli->run('git rev-parse --short HEAD', function ($e, $o): void {
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

        $branch = $this->cli->run($command, function ($e, $o): void {
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
            $this->cli->run('git reset --hard', null, $path);
            $this->cli->run('git clean -d -f .', null, $path);
        }

        $switched = $this->cli->run("git checkout ${branchName}", function ($e, $o): void {
            throw new RuntimeException('Failed to switch branch');
        }, $path);

        return Str::replace([PHP_EOL, "\n"], '', $switched);
    }

    public function clone(string $package, string $path): void
    {
        $token = getenv('GITHUB_TOKEN');
        $cmd = "git clone https://${token}@github.com/processmaker/${package}";

        $this->cli->runCommand($cmd, function ($code, $output): void {
            throw new RuntimeException($output);
        }, $path);
    }
}
