<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\FileSystem;

class Logs
{
    /**
     * Get an array of application and system log file paths
     *
     * @return array
     */
    public function all(): array
    {
        return array_merge(
            $this->getApplicationLogs(),
            $this->getSystemLogs()
        );
    }

    /**
     * Get the application log file path(s)
     *
     * @return array
     */
    public function getApplicationLogs(): array
    {
        if (!FileSystem::exists($logs_directory = codebase_path('storage/logs'))) {
            throw new \RuntimeException('Application logs directory not found');
        }

        $log_directory_files = array_map(
            static function ($filename) use ($logs_directory) {
                return "{$logs_directory}/{$filename}";
            }, FileSystem::scandir($logs_directory));

        $log_files = array_values(array_filter($log_directory_files,
            static function ($path) {
                return Str::endsWith($path, '.log');
            }
        ));

        if (blank($log_files)) {
            throw new \RuntimeException('No application log files found');
        }

        return $log_files;
    }

    /**
     * Get the system log file path(s)
     *
     * @return array
     */
    public function getSystemLogs(): array
    {
        if (!FileSystem::exists($logs_directory = logs_path())) {
            throw new \RuntimeException('System logs directory not found');
        }

        $log_directory_files = array_map(
            static function ($filename) use ($logs_directory) {
                return "{$logs_directory}/{$filename}";
            }, FileSystem::scandir($logs_directory));

        $log_files = array_values(array_filter($log_directory_files,
            static function ($path) {
                return Str::endsWith($path, '.log');
            }
        ));

        if (blank($log_files)) {
            throw new \RuntimeException('No system log files found');
        }

        return $log_files;
    }
}
