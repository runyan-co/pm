<?php

namespace ProcessMaker\Cli;

use \LogicException, \RuntimeException;
use Silly\Command\Command;
use React\ChildProcess\Process;
use \CommandLine as CommandLineFacade;
use \FileSystem as FileSystemFacade;
use Illuminate\Support\Str;

class Git
{
    public function validateGitRepository(string $path)
    {
        if (!FileSystemFacade::isDir($path)) {
            throw new LogicException("Directory to git repository does not exist: $path");
        }

        if (!FileSystemFacade::exists("$path/.git")) {
            throw new LogicException("Git repository not found in directory: $path");
        }
    }

    /**
     * @param  string  $path_to_repo
     *
     * @return string
     */
    public function getCurrentCommitHash(string $path_to_repo): string
    {
        $this->validateGitRepository($path_to_repo);

        $output = CommandLineFacade::runAsUser('git rev-parse --short HEAD', function ($e, $o) {
            throw new RuntimeException('Error trying to retrieve current git commit hash.');
        }, $path_to_repo);

        return Str::replace([PHP_EOL, "\n"], '', $output);
    }

    public function getDefaultBranch(string $path): string
    {
        $this->validateGitRepository($path);

        $command = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";

        $branch = CommandLineFacade::runAsUser($command, function ($e, $o) {
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
            CommandLineFacade::runAsUser('git reset --hard', null, $path);
            CommandLineFacade::runAsUser('git clean -d -f .', null, $path);
        }

        $switched = CommandLineFacade::runAsUser("git checkout $branchName", function ($e, $o) {
            throw new RuntimeException('Failed to switch branch');
        }, $path);

        return Str::replace([PHP_EOL, "\n"], '', $switched);
    }
}
