<?php

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\FileSystem;

class Logs
{
    /**
     * Get the application log file path(s)
     *
     * @return array
     */
    public function getApplicationLogs(): array
    {
        $log_directory_files = FileSystem::scandir(
            codebase_path('storage/logs')
        );

        return array_values(array_filter($log_directory_files, static function ($path) {
            return Str::endsWith($path, '.log');
        }));
    }
}
