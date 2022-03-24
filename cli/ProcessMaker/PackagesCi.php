<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use ProcessMaker\Facades\Config;
use ProcessMaker\Facades\Git;

class PackagesCi
{
    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function install(): void
    {
        $packages = resolve(Packages::class);
        $list = $packages->getSupportedPackages();

        // Clone packages
        foreach ($list as $package) {
            Git::clone($package, Config::packagesPath());
        }

        Composer::addRepositoryPath();

        $listString = $this->composerRequireList($list);

        Composer::require($listString);
    }

    /**
     * @param  array  $list
     */
    private function composerRequireList(array $list): string
    {
        return collect($list)->map(function ($package) {
            return "processmaker/${package}";
        })->join(' ');
    }
}
