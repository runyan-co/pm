<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Support\Collection;
use ProcessMaker\Cli\Facades\Packages;
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
        $packages = Packages::getInstance();
        $list = $packages->getSupportedPackages();

        // Clone packages
        foreach ($list as $package) {
            Git::clone($package, packages_path());
        }

        Composer::addRepositoryPath();
        Composer::require($this->composerRequireList($list));
    }

    /**
     * @param  array  $list
     *
     * @return string
     */
    private function composerRequireList(array $list): string
    {
        return Collection::make($list)->map(function ($package) {
            return "processmaker/${package}";
        })->join(' ');
    }
}
