<?php

namespace ProcessMaker\Cli;

class ProcessMaker
{
    public $files;

    public $cli;

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

        $this->files->put('/etc/sudoers.d/brew', 'Cmnd_Alias BREW = '.BREW_PREFIX.'/bin/brew *
%admin ALL=(root) NOPASSWD:SETENV: BREW'.PHP_EOL);
    }

    /**
     * Remove the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    function removeSudoersEntry()
    {
        $this->cli->quietly('rm /etc/sudoers.d/pm');

        $this->cli->quietly('rm /etc/sudoers.d/brew');
    }
}
