<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use ProcessMaker\Cli\Facades\CommandLine as Cli;
use ProcessMaker\Cli\Facades\FileSystem;

class Install
{
    public $bin = HOMEBREW_PREFIX.'/bin/pm';

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
    public function install(array $config_values): void
    {
        $this->unlinkFromUsersBin();

        $this->symlinkToUsersBin();

        $this->createConfigurationDirectory();

        $this->write($config_values);

        FileSystem::chown($this->path(), user());
    }

    public function installed(): bool
    {
        return FileSystem::exists(PM_HOME_PATH);
    }

    /**
     * Forcefully delete the Valet home configuration directory and contents.
     */
    public function uninstall(): void
    {
        FileSystem::unlink(PM_HOME_PATH);
    }

    /**
     * Create the Valet configuration directory.
     */
    public function createConfigurationDirectory(): void
    {
        FileSystem::ensureDirExists(PM_HOME_PATH, user());
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function get(string $key)
    {
        return ! blank(($value = resolve(self::class)->read($key))) ? $value : null;
    }

    /**
     * Read the configuration file as JSON.
     *
     * @param  string|null  $key
     *
     * @return array|string
     */
    public function read(string $key = null)
    {
        $json = json_decode(FileSystem::get($this->path()), true);

        if ($key && is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $json;
    }

    /**
     * Update a specific key in the configuration file.
     *
     * @param  string  $key
     * @param  mixed  $value
     *
     * @return array
     * @throws \JsonException
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
        FileSystem::putAsUser(
            $this->path(),
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
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
