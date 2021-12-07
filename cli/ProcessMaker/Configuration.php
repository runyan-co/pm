<?php

namespace ProcessMaker\Cli;

class Configuration
{
    public $files;

    /**
     * Create a new Valet configuration class instance.
     *
     * @param Filesystem $files
     */
    public function __construct(FileSystem $files)
    {
        $this->files = $files;
    }

    /**
     * Install the Valet configuration file.
     *
     * @return void
     */
    public function install(): void
    {
        $this->createConfigurationDirectory();

        $this->createConfigurationFile();

        $this->files->chown($this->path(), user());
    }

    public function createConfigurationFile(): void
    {
        $this->write([
            'codebase_path' => '',
            'packages_path' => ''
        ]);
    }

    /**
     * Forcefully delete the Valet home configuration directory and contents.
     *
     * @return void
     */
    public function uninstall(): void
    {
        $this->files->unlink(PM_HOME_PATH);
    }

    /**
     * Create the Valet configuration directory.
     *
     * @return void
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
     * @param  string  $key
     * @param  mixed  $value
     *
     * @return array
     */
    public function updateKey(string $key, $value): array
    {
        return tap($this->read(), function (&$config) use ($key, $value) {
            $config[$key] = $value;
            $this->write($config);
        });
    }

    /**
     * Write the given configuration to disk.
     *
     * @param  array  $config
     *
     * @return void
     */
    public function write(array $config): void
    {
        $this->files->putAsUser($this->path(), json_encode(
            $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /**
     * Get the configuration file path.
     *
     * @return string
     */
    public function path(): string
    {
        return PM_HOME_PATH.'/config.json';
    }
}
