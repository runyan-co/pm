<?php

namespace ProcessMaker\Cli;

class ProcessMaker
{
    public $files;

    public $cli;

    public function __construct()
    {
        $this->files = new FileSystem();
        $this->cli = new CommandLine();
    }

    /**
     * Create the "sudoers.d" entry for running Valet.
     *
     * @return void
     */
    function createSudoersEntry()
    {
        $this->files->ensureDirExists('/etc/sudoers.d');

        $this->files->put('/etc/sudoers.d/pm', 'Cmnd_Alias VALET = '.PM_PREFIX.'/bin/pm *
%admin ALL=(root) NOPASSWD:SETENV: VALET'.PHP_EOL);
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
}
