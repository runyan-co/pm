<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use ProcessMaker\Facades\CommandLine as Cli;

class Install
{
    public FileSystem $files;

    public string $bin = HOMEBREW_PREFIX.'/bin/pm';

    /**
     * @param  \ProcessMaker\Cli\FileSystem  $files
     */
    public function __construct(FileSystem $files)
    {
        $this->files = $files;
    }

    /**
     * @return void
     */
    public function symlinkToUsersBin(): void
    {
        $this->unlinkFromUsersBin();

        Cli::run('ln -s "'.realpath(__DIR__.'/../../pm').'" '.$this->bin);
    }

    /**
     * Remove the symlink from the user's local bin.
     */
    public function unlinkFromUsersBin(): void
    {
        Cli::quietly('rm '.$this->bin);
    }

    /**
     * Install the Valet configuration file.
     */
    public function install(string $codebase_path, string $packages_path): void
    {
        $this->unlinkFromUsersBin();

        $this->symlinkToUsersBin();

        $this->createConfigurationDirectory();

        $this->write([
            'codebase_path' => $codebase_path,
            'packages_path' => $packages_path,
        ]);

        $this->files->chown($this->path(), user());
    }

    /**
     * Forcefully delete the Valet home configuration directory and contents.
     */
    public function uninstall(): void
    {
        $this->files->unlink(PM_HOME_PATH);
    }

    /**
     * Create the Valet configuration directory.
     */
    public function createConfigurationDirectory(): void
    {
        $this->files->ensureDirExists(PM_HOME_PATH, user());
    }

    /**
     * Read the configuration file as JSON.
     *
     * @return array
     */
    public function read(): array
    {
        return json_decode($this->files->get($this->path()), true);
    }

    /**
     * Update a specific key in the configuration file.
     *
     * @param  mixed  $value
     *
     * @return array
     */
    public function updateKey(string $key, $value): array
    {
        return tap($this->read(), function (&$config) use ($key, $value): void {
            $config[$key] = $value;
            $this->write($config);
        });
    }

    /**
     * Write the given configuration to disk.
     *
     * @param  array  $config
     */
    public function write(array $config): void
    {
        $this->files->putAsUser(
            $this->path(),
            json_encode(
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ).PHP_EOL
        );
    }

    /**
     * Get the configuration file path.
     */
    public function path(): string
    {
        return PM_HOME_PATH.'/config.json';
    }
}
