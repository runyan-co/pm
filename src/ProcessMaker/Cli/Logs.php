<?php

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;

class Logs
{
    /**
     * @var \ProcessMaker\Cli\FileSystem
     */
    protected $files;

    /**
     * @param  \ProcessMaker\Cli\FileSystem  $files
     */
    public function __construct(FileSystem $files)
    {
        $this->files = $files;
    }

    /**
     * Get the application log file path(s)
     *
     * @return array
     */
    public function getApplicationLogs(): array
    {
        $log_directory_files = $this->files->scandir(
            codebase_path('storage/logs')
        );

        return array_values(array_filter($log_directory_files, static function ($path) {
            return Str::endsWith($path, '.log');
        }));
    }
}
