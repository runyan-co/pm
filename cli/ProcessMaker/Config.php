<?php

namespace ProcessMaker\Cli;

use \Install;

class Config
{
    public function packagesPath()
    {
        $path = getenv('PACKAGES_PATH');
        if (!$path) {
            $path = Install::read()['packages_path'];
        }
        return $path;
    }

    public function codebasePath()
    {
        $path = getenv('CODEBASE_PATH');
        if (!$path) {
            $path = Install::read()['codebase_path'];
        }
        return $path;
    }
}