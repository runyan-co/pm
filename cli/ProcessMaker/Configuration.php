<?php

namespace ProcessMaker\Cli;

class Configuration
{
    public $files;

    public function __construct(FileSystem $files)
    {
        $this->files = $files;
    }
}
