<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use ProcessMaker\Cli\Facades\Config;
use ProcessMaker\Cli\Facades\Git;

class PackagesCi
{
    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Exception
     * @throws \Exception
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
     *
     * @return string
     */
    private function composerRequireList(array $list): string
    {
        return collect($list)->map(function ($package) {
            return "processmaker/${package}";
        })->join(' ');
    }
}
