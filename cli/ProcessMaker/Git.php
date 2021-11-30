<?php

namespace ProcessMaker\Cli;

use LogicException;
use Illuminate\Support\Str;
use \FileSystem as FileSystemFacade;

class Git
{
    public $cli;

    public function __construct(CommandLine $cli) {
        $this->cli = $cli;
    }

    public function validatePathToRepo(string $path)
    {
        if (!FileSystemFacade::isDir($path)) {
            throw new LogicException("Directory to git repo does not exist: $path");
        }
    }

    public function getDefaultBranch(string $path): string
    {
        $this->validatePathToRepo($path);

        $command = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";

        $branch = $this->cli->runAsUser($command, function ($e, $o) {
            warning('Could not find default git branch.'); output($o);
        }, $path);

        return Str::replace([PHP_EOL, "\n"], '', $branch);
    }

    /**
     * @param  string  $branchName
     * @param  string  $path
     *
     * @return string
     */
    public function switchBranch(string $branchName, string $path): string
    {
        $this->validatePathToRepo($path);

        $switched = $this->cli->runAsUser("git checkout $branchName", function ($e, $o) {
            warning('Failed to switch git branch.'); output($o);
        }, $path);

        return Str::replace([PHP_EOL, "\n"], '', $switched);
    }
}
