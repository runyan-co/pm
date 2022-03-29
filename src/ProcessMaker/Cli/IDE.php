<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;

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
     */
    public static $tmp = 'tmp';

    /**
     * @var \ProcessMaker\Cli\FileSystem
     */
    protected $files;

    /**
     * @return void
     */
    public function __construct(FileSystem $files)
    {
        $this->files = $files;
    }

    /**
     * Check if a given path contains a project-specific IDE configuration file. If a
     * path is not passed as an argument, it will check in the local core codebase.
     *
     * @return string|void
     */
    public function hasConfiguration(?string $path = null)
    {
        foreach (self::$types as $ide => $file_name) {
            if ($this->files->exists($path = ($path ? "{$path}/{$file_name}" : codebase_path($file_name)))) {
                return $path;
            }
        }
    }

    /**
     * Move temporarily stored IDE config file(s) from the tmp directory
     * back to its respective project directory
     */
    public function moveConfigurationBack(string $from, ?string $to = null): void
    {
        if (! $this->files->exists($from)) {
            return;
        }

        if (! $this->files->exists($to = $to ?? codebase_path())) {
            return;
        }

        if (! $this->files->mv($from, $to)) {
            return;
        }

        $this->files->rmdir(Str::remove(basename($from), $from));
    }

    /**
     * Move a given directory's IDE configuration file (if one is present) to a temp directory
     *
     * @return string|void
     */
    public function temporarilyMoveConfiguration(?string $path = null)
    {
        if (! $this->files->exists($path = $path ?? codebase_path())) {
            return;
        }

        // Name of the directory containing the config file
        $basename = strtolower(basename($path));

        // Check for config files, if one exists then $path will
        // become the absolute path to the config file
        if (! ($path = $this->hasConfiguration($path))) {
            return;
        }

        // Config filename
        $filename = basename($path);

        // Create the tmp/ directory if it doesn't exist
        if (! $this->files->exists($tmp_path = pm_path(self::$tmp))) {
            $this->files->mkdir($tmp_path);
        }

        // Create the project-specific directory name within the tmp
        // directory (in case we have other config files present)
        if (! $this->files->exists($move_to_path = "{$tmp_path}/{$basename}")) {
            $this->files->mkdir($move_to_path);
        }

        // Move the config files to the tmp directory and return it
        if ($this->files->mv($path, $move_to_path)) {
            return "{$move_to_path}/{$filename}";
        }
    }
}
