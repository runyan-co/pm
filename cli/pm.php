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
use function ProcessMaker\Cli\table;
use function ProcessMaker\Cli\info;

Container::setInstance(new Container);

$app = new Application('ProcessMaker CLI Tool', '0.5.0');

$app->command('pull [-4|--for_41_develop]', function (InputInterface $input, OutputInterface $output) {

    // Updates to 4.1-branch of packages (or not)
    $for_41_develop = $input->getOption('for_41_develop');

	// Set verbosity level of output
    $verbose = $input->getOption('verbose');

	// Put everything together and run it
	Packages::pull($for_41_develop, $verbose);
});

$app->command('trust [--off]', function ($off) {
	if ($off) {
        ProcessMaker::unlinkFromUsersBin();
        ProcessMaker::removeSudoersEntry();

        return info('ProcessMaker CLI tool removed sudoers file.');
	}

    ProcessMaker::symlinkToUsersBin();
    ProcessMaker::createSudoersEntry();

    info('ProcessMaker CLI tool added to sudoers file.');

})->descriptions('Adds the pm cli tool to the sudoers file.');

$app->command('pull-packages [-4|--for_41_develop] [-d|--directory]',
	function ($for_41_develop = null, $directory = null) {

		info($for_41_develop
			? "Updating local ProcessMaker composer packages to 4.1-develop..."
			: "Updating local ProcessMaker composer packages...");

        table(['Package', 'Version', 'Updated Version', '4.1', 'Errors'],
            Packages::pullPackages($for_41_develop ?? false, $directory));

	}
)->descriptions('Iterate through each ProcessMaker package (locally) and pull down any updates from GitHub.');

$app->run();
