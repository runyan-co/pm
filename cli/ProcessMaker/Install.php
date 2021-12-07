<?php

namespace ProcessMaker\Cli;

use \CommandLine as CommandLineFacade;

class Install
{
    public $bin = BREW_PREFIX.'/bin/pm';

    public $files;

    public function __construct(FileSystem $files)
    {
        $this->files = $files;
    }

    /**
     * Create the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    public function createSudoersEntry()
    {
        $this->ensureDirExists('/etc/sudoers.d');

        $this->put('/etc/sudoers.d/pm', 'Cmnd_Alias PM = '.BREW_PREFIX.'/bin/pm *
%admin ALL=(root) NOPASSWD:SETENV: PM'.PHP_EOL);
    }

    /**
     * Remove the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    public function removeSudoersEntry()
    {
        CommandLineFacade::quietly('rm /etc/sudoers.d/pm');
    }

    public function symlinkToUsersBin()
    {
        $this->unlinkFromUsersBin();

        CommandLineFacade::runAsUser('ln -s "'.realpath(__DIR__.'/../../pm').'" '.$this->bin);
    }

    /**
     * Remove the symlink from the user's local bin.
     *
     * @return void
     */
    public function unlinkFromUsersBin()
    {
        CommandLineFacade::quietlyAsUser('rm '.$this->bin);
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
