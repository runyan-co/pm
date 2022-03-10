<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use ProcessMaker\Facades\Install;

class Config
{
    /**
     * @return array|false|mixed|string
     */
    public function packagesPath()
    {
        $path = getenv('PACKAGES_PATH');

        if (! $path) {
            $path = Install::read()['packages_path'];
        }

        return $path;
    }

    /**
     * @param  string|null  $file_name
     *
     * @return array|false|mixed|string
     */
    public function codebasePath(?string $file_name = null)
    {
        $path = getenv('CODEBASE_PATH');

        if (! $path) {
            $path = Install::read()['codebase_path'];
        }

        return $file_name ? "${path}/${file_name}" : $path;
    }
}
