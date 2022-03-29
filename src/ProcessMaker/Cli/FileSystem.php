<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use RuntimeException;
use ProcessMaker\Cli\Facades\CommandLine as Cli;

class FileSystem
{
    /**
     * Determine if the given path is a directory.
     */
    public function is_dir(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Move a directory
     */
    public function mv(string $from_path, string $to_path): bool
    {
        try {
            Cli::run("mv ${from_path} ${to_path}", static function ($error, $output): void {
                throw new RuntimeException($output);
            });

            return true;
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    /**
     * Remove a directory
     */
    public function rmdir(string $path): bool
    {
        if (! $this->is_dir($path)) {
            return false;
        }

        try {
            Cli::run("rm -rf ${path}", function ($error, $output): void {
                throw new RuntimeException($output);
            });

            return true;
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    /**
     * Create a directory.
     */
    public function mkdir(string $path, ?string $owner = null, int $mode = 0755): void
    {
        if (! mkdir($path, $mode, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        if ($owner) {
            $this->chown($path, $owner);
        }
    }

    /**
     * Ensure that the given directory exists.
     */
    public function ensureDirExists(string $path, ?string $owner = null, int $mode = 0755): void
    {
        if (! $this->is_dir($path)) {
            $this->mkdir($path, $owner, $mode);
        }
    }

    /**
     * Create a directory as the non-root user.
     */
    public function mkdirAsUser(string $path, int $mode = 0755): void
    {
        $this->mkdir($path, user(), $mode);
    }

    /**
     * Touch the given path.
     */
    public function touch(string $path, ?string $owner = null): string
    {
        touch($path);

        if ($owner) {
            $this->chown($path, $owner);
        }

        return $path;
    }

    /**
     * Touch the given path as the non-root user.
     */
    public function touchAsUser(string $path): string
    {
        return $this->touch($path, user());
    }

    /**
     * Determine if the given file exists.
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Read the contents of the given file.
     */
    public function get(string $path): string
    {
        return file_get_contents($path);
    }

    /**
     * Write to the given file.
     */
    public function put(string $path, string $contents, ?string $owner = null): void
    {
        file_put_contents($path, $contents);

        if ($owner) {
            $this->chown($path, $owner);
        }
    }

    /**
     * Write to the given file as the non-root user.
     */
    public function putAsUser(string $path, string $contents): void
    {
        $this->put($path, $contents, user());
    }

    /**
     * Append the contents to the given file.
     */
    public function append(string $path, string $contents, ?string $owner = null): void
    {
        file_put_contents($path, $contents, FILE_APPEND);

        if ($owner) {
            $this->chown($path, $owner);
        }
    }

    /**
     * Append the contents to the given file as the non-root user.
     */
    public function appendAsUser(string $path, string $contents): void
    {
        $this->append($path, $contents, user());
    }

    /**
     * Copy the given file to a new location.
     */
    public function copy(string $from, string $to): void
    {
        copy($from, $to);
    }

    /**
     * Copy the given file to a new location for the non-root user.
     */
    public function copyAsUser(string $from, string $to): void
    {
        copy($from, $to);

        $this->chown($to, user());
    }

    /**
     * Create a symlink to the given target.
     */
    public function symlink(string $target, string $link): void
    {
        if ($this->exists($link)) {
            $this->unlink($link);
        }

        symlink($target, $link);
    }

    /**
     * Create a symlink to the given target for the non-root user.
     *
     * This uses the command line as PHP can't change symlink permissions.
     */
    public function symlinkAsUser(string $target, string $link): void
    {
        if ($this->exists($link)) {
            $this->unlink($link);
        }

        Cli::run('ln -s '.escapeshellarg($target).' '.escapeshellarg($link));
    }

    /**
     * Delete the file at the given path.
     */
    public function unlink(string $path): void
    {
        if (file_exists($path) || is_link($path)) {
            @unlink($path);
        }
    }

    /**
     * Change the owner of the given path.
     */
    public function chown(string $path, string $user): void
    {
        chown($path, $user);
    }

    /**
     * Change the group of the given path.
     */
    public function chgrp(string $path, string $group): void
    {
        chgrp($path, $group);
    }

    /**
     * Resolve the given path.
     */
    public function realpath(string $path): string
    {
        return realpath($path);
    }

    /**
     * Determine if the given path is a symbolic link.
     */
    public function isLink(string $path): bool
    {
        return is_link($path);
    }

    /**
     * Resolve the given symbolic link.
     */
    public function readLink(string $path): string
    {
        return readlink($path);
    }

    /**
     * Remove all of the broken symbolic links at the given path.
     */
    public function removeBrokenLinksAt(string $path): void
    {
        collect($this->scandir($path))->filter(function ($file) use ($path) {
            return $this->isBrokenLink($path.'/'.$file);
        })->each(function ($file) use ($path): void {
            $this->unlink($path.'/'.$file);
        });
    }

    /**
     * Determine if the given path is a broken symbolic link.
     */
    public function isBrokenLink(string $path): bool
    {
        return is_link($path) && ! file_exists($path);
    }

    /**
     * Scan the given directory path.
     *
     * @param  string  $path
     *
     * @return array
     */
    public function scandir(string $path): array
    {
        return collect(scandir($path))->reject(function ($file) {
            return in_array($file, ['.', '..']);
        })->values()->all();
    }
}
