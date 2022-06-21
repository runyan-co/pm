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
        if (!FileSystem::exists($logs_directory = codebase_path('storage/logs'))) {
            throw new \RuntimeException('Logs directory not found');
        }

        $log_directory_files = FileSystem::scandir($logs_directory);

        $log_files = array_values(array_filter($log_directory_files, static function ($path) {
            return Str::endsWith($path, '.log');
        }));

        if (blank($log_files)) {
            throw new \RuntimeException('No log files found');
        }

        return $log_files;
    }
}
