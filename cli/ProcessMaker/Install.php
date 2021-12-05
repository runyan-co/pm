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
}
