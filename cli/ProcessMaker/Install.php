<?php

namespace ProcessMaker\Cli;

use \FileSystem as FileSystemFacade;
use \CommandLine as CommandLineFacade;

class Install
{
    public $bin = BREW_PREFIX.'/bin/pm';

    /**
     * Create the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    function createSudoersEntry()
    {
        FileSystemFacade::ensureDirExists('/etc/sudoers.d');

        FileSystemFacade::put('/etc/sudoers.d/pm', 'Cmnd_Alias PM = '.BREW_PREFIX.'/bin/pm *
%admin ALL=(root) NOPASSWD:SETENV: PM'.PHP_EOL);
    }

    /**
     * Remove the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    function removeSudoersEntry()
    {
        CommandLineFacade::quietly('rm /etc/sudoers.d/pm');
    }

    function symlinkToUsersBin()
    {
        $this->unlinkFromUsersBin();

        CommandLineFacade::runAsUser('ln -s "'.realpath(__DIR__.'/../../pm').'" '.$this->bin);
    }

    /**
     * Remove the symlink from the user's local bin.
     *
     * @return void
     */
    function unlinkFromUsersBin()
    {
        CommandLineFacade::quietlyAsUser('rm '.$this->bin);
    }
}
