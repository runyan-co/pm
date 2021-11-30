#!/usr/bin/env php
<?php

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
} elseif (file_exists(__DIR__.'/../../../autoload.php')) {
    require __DIR__.'/../../../autoload.php';
} else {
    require getenv('HOME').'/.composer/vendor/autoload.php';
}

use Silly\Application;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function ProcessMaker\Cli\info;
use function ProcessMaker\Cli\warning;

Container::setInstance(new Container);

$app = new Application('ProcessMaker CLI Tool', '0.5.0');

$app->command('pull [-4|--for_41_develop]', function (InputInterface $input, OutputInterface $output) {

    // Updates to 4.1-branch of packages (or not)
    $for_41_develop = $input->getOption('for_41_develop');

	// Set verbosity level of output
    $verbose = $input->getOption('verbose');

	// Put everything together and run it
	$for_41_develop ? Packages::pull41($verbose) : Packages::pull($verbose);
})->descriptions('Cycles through each local store of supported ProcessMaker 4 packages.',
	['--for_41_develop' => 'Change each package to the correct version for the 4.1 version of processmaker/processmaker']
);

$app->command('clone-all', function () {
	try {
        if (Packages::cloneAllPackages()) {
            info('All ProcessMaker packages cloned successfully!');
        }
	} catch (Exception $exception) {
		warning($exception->getMessage());
	}
});

$app->command('clone package [-f|--force]', function ($package, $force = null) {
	try {
		if (Packages::clonePackage($package, $force)) {
			info("$package cloned successfully!");
		}
	} catch (Exception $exception) {
		warning($exception->getMessage());
	}
});

$app->command('trust [--off]', function ($off) {
	if ($off) {
        Install::unlinkFromUsersBin();
        Install::removeSudoersEntry();

        return info('ProcessMaker CLI tool removed sudoers file.');
	}

    Install::symlinkToUsersBin();
    Install::createSudoersEntry();

    info('ProcessMaker CLI tool added to sudoers file.');

})->descriptions('Adds the pm cli tool to the sudoers file.');

$app->run();
