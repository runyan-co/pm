<?php

namespace ProcessMaker\Cli;

use ProcessMaker\Facades\Git;
use ProcessMaker\Facades\Config;

class PackagesCi {
    public function install()
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

    private function composerRequireList($list)
    {
        return collect($list)->map(function($package) {
            return "processmaker/$package";
        })->join(" ");
    }
}
