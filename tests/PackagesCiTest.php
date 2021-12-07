<?php

use PHPUnit\Framework\TestCase;
use ProcessMaker\Cli\CommandLine;
use \PackagesCi;
use function ProcessMaker\Cli\swap;

class PackagesCiTest extends TestCase
{
    public function setUp() : void
    {
        $this->codebasePath = __DIR__ . '/Fixtures/codebase';
        putenv("CODEBASE_PATH=$this->codebasePath");
        $this->packagesPath = __DIR__ . '/Fixtures/packages';
        putenv("PACKAGES_PATH=$this->packagesPath");
        $this->token = 'abc123';
        putenv("GITHUB_TOKEN=$this->token");
    }

    public function testCiInstall()
    {
        $cli = Mockery::mock(CommandLine::class);
        $any = Mockery::any();
        $cli->shouldReceive('runAsUser')->withAnyArgs()->andReturn(0);
        $cli->shouldReceive('runCommand')
            ->with(Mockery::pattern('|git clone https://abc123@github.com/processmaker/.+|'), $any, $this->packagesPath)
            ->times(10)
            ->andReturn(0);
        $cli->shouldReceive('runCommand')
            ->with("composer config repositories.pm4-packages path $this->packagesPath/*", $any, $this->codebasePath)
            ->once()
            ->andReturn(0);
        $cli->shouldReceive('runCommand')
            ->with(Mockery::pattern('|composer require .*processmaker/some-package.*|'), $any, $this->codebasePath)
            ->once()
            ->andReturn(0);

        swap(CommandLine::class, $cli);

        PackagesCi::install();
        Mockery::close();
    }
}