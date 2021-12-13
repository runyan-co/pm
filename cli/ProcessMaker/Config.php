<?php

namespace ProcessMaker\Cli;

use ProcessMaker\Facades\Install;

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

    public function codebasePath(string $file_name = null)
    {
        $path = getenv('CODEBASE_PATH');

        if (!$path) {
            $path = Install::read()['codebase_path'];
        }

        return $file_name ? "$path/$file_name" : $path;
    }
}
