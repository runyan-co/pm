#!/usr/bin/env php
<?php

/**
 * Load correct autoloader depending on install location.
 */

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
} elseif (file_exists(__DIR__.'/../../../autoload.php')) {
    require __DIR__.'/../../../autoload.php';
} else {
    require getenv('HOME').'/.composer/vendor/autoload.php';
}

use Silly\Application;
use ProcessMaker\Cli\ProcessMaker;
use ProcessMaker\Cli\Packages;
use Illuminate\Container\Container;
use function ProcessMaker\Cli\table;
use function ProcessMaker\Cli\info;

Container::setInstance(new Container);

$app = new Application('ProcessMaker CLI Tool', '0.5.0');

$app->command('trust', function () {
	ProcessMaker::createSudoersEntry();

	info('pm added to sudoers file.');
})->descriptions('Adds the pm cli tool to the sudoers file.');

$app->command('pull-packages [-4|--for_41_develop] [-d|--directory]',
	function ($for_41_develop = null, $directory = null) {

        table(['Package', 'Version', 'Updated Version', '4.1'],
            Packages::pullPackages($for_41_develop ?? false, $directory));

	}
)->descriptions('Iterate through each ProcessMaker package (locally) and pull down any updates from GitHub.');;

$app->run();
