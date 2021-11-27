<?php

namespace ProcessMaker\Cli;

class ProcessMaker
{
    public $files;

    public $cli;

    protected static $bin = BREW_PREFIX.'/bin/pm';

    public function __construct(FileSystem $files, CommandLine $cli)
    {
        $this->files = $files;
        $this->cli = $cli;
    }

    /**
     * Create the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    function createSudoersEntry()
    {
        $this->files->ensureDirExists('/etc/sudoers.d');

        $this->files->put('/etc/sudoers.d/pm', 'Cmnd_Alias PM = '.BREW_PREFIX.'/bin/pm *
%admin ALL=(root) NOPASSWD:SETENV: PM'.PHP_EOL);
    }

    /**
     * Remove the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    function removeSudoersEntry()
    {
        $this->cli->quietly('rm /etc/sudoers.d/pm');
    }

    function symlinkToUsersBin()
    {
        $this->unlinkFromUsersBin();

        $this->cli->runAsUser('ln -s "'.realpath(__DIR__.'/../../pm').'" '.$this->bin);
    }

    /**
     * Remove the symlink from the user's local bin.
     *
     * @return void
     */
    function unlinkFromUsersBin()
    {
        $this->cli->quietlyAsUser('rm '.$this->bin);
    }
}
