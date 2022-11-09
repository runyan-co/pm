<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\FileSystem;

class IDE
{
    /**
     * Supported IDEs and their respective project-specific
     * configuration directory names
     *
     * @var array<string>
     */
    public static $types = [
        'phpstorm' => '.idea',
        'vscode' => '.vscode',
    ];

    /**
     * The temporary directory name
     *
     * @var false|string
     */
    public static $tmp = false;

    /**
     * Check if a given path contains a project-specific IDE
     * configuration file. If a path is not passed as an
     * argument, it will check in the local core codebase.
     *
     * @param  string|null  $path
     *
     * @return false|string
     */
    public static function getConfigurationPath(string $path = null)
    {
        foreach (self::$types as $ide => $file_name) {
            if (FileSystem::exists($path = ($path ? "{$path}/{$file_name}" : codebase_path($file_name)))) {
                return $path;
            }
        }
    }

    /**
     * Move temporarily stored IDE config file(s) from the tmp directory
     * back to its respective project directory
     */
    public static function moveConfigurationBack(string $from, string $to = null): void
    {
        if (!FileSystem::exists($from)) {
            return;
        }

        if (!FileSystem::exists($to = $to ?? codebase_path())) {
            return;
        }

        if (!FileSystem::mv($from, $to)) {
            return;
        }

        FileSystem::rmdir(Str::remove(basename($from), $from));
    }

    /**
     * Move a given directory's IDE configuration file (if one is present) to a temp directory
     *
     * @return string|void
     */
    public static function temporarilyMoveConfiguration(string $path = null)
    {
        if (!FileSystem::exists($path = $path ?? codebase_path())) {
            return;
        }

        // Name of the directory containing the config file
        $basename = strtolower(basename($path));

        // Check for config files, if one exists then $path will
        // become the absolute path to the config file
        if (!is_string($path = self::getConfigurationPath($path))) {
            return;
        }

        // Config filename
        $filename = basename($path);

        // Find where we should write a new temp dir
        if (file_exists(static::$tmp = tempnam(sys_get_temp_dir(), $filename))) {
            unlink(static::$tmp);
        }

        // Create the project-specific directory name within the tmp
        // directory (in case we have other config files present)
        FileSystem::mkdir($move_to_path = static::$tmp."/{$basename}");

        // Move the config files to the tmp directory and return it
        if (FileSystem::mv($path, $move_to_path)) {
            return "{$move_to_path}/{$filename}";
        }
    }
}
