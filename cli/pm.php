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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use function ProcessMaker\Cli\info;
use function ProcessMaker\Cli\warning;
use function ProcessMaker\Cli\output;
use function ProcessMaker\Cli\resolve;

Container::setInstance(new Container());

$app = new Application('ProcessMaker CLI Tool', '0.5.0');

$app->command('install-packages [-4|--for_41_develop]', function (InputInterface $input, OutputInterface $output) {

	// Indicates if we should install the 4.1-develop
	// versions of each package or the 4.2
	$for_41_develop = $input->getOption('for_41_develop');

	// Should the output be verbose or not
	$verbose = $input->getOption('verbose');

	// Use an anonymous function to we can easily re-run if
	// we decide to force the installation of the packages
    $build_install_commands = static function ($force = false) use ($for_41_develop) {
        return Packages::buildPackageInstallCommands($for_41_develop, $force);
    };

	// Builds an array of commands to run in the local
	// processmaker/processmaker codebase to require
	// each supported package, then install it and
	// publish it's vendor assets (if any are available)
    try {
        $install_commands = $build_install_commands();
    } catch (DomainException $exception) {

		// Show the user the incompatible branch information
		warning($exception->getMessage());

		// Ask the user if they want to force the install anyway
        $helper = $this->getHelperSet()->get('question');
        $question = new ConfirmationQuestion('<comment>Force install the packages?</comment> "No" to abort or "Yes" to proceed: ', false);

		// Bail out if they user doesn't want to force the install
        if (false === $helper->ask($input, $output, $question)) {
            return warning('Packages install aborted.');
        }

        // Re-run the install but add the force argument to
        // prevent the incompatible exception from being thrown
        $install_commands = $build_install_commands(true);
    }

    // Keep the user in the loop
    info('Installing packages...'.PHP_EOL);

	// Grab an instance of the CommandLine class
	$cli = resolve(\ProcessMaker\Cli\CommandLine::class);

	// Create a progress bar and start it
	$cli->createProgressBar($install_commands->flatten()->count(), 'message');

	// Set the initial message and start up the progress bar
	$cli->getProgress()->setMessage('Starting install...');
	$cli->getProgress()->start();

	// Iterate through the collection of commands
    foreach ($install_commands as $package => $command_collection) {

		// Iterate through each command and attempt to run it
		$command_collection->each(static function ($command) use ($cli, $package, $verbose) {

            // Update the progress bar
            $cli->getProgress()->setMessage("Installing $package...");
            $cli->getProgress()->advance();

            try {
                $output = $cli->runAsUser($command, static function ($exitCode, $output) {
                    throw new RuntimeException($output);
                }, CODEBASE_PATH);
            } catch (RuntimeException $exception) {
                $cli->getProgress()->clear();

                output("<fg=red>Command Failed:</> $command");
                output($exception->getMessage());

                $cli->getProgress()->display();

				return;
            }

			// If the user wants verbose output, then show the
			// stdout for all of the successful commands as well
			// (in addition to the ones which failed)
            if ($verbose) {
                $cli->getProgress()->clear();

                output("<info>Command Success:</info> $command");
                output($output);

                $cli->getProgress()->display();
            }
		});
    }

	// Clean up the progress bar
	$cli->getProgress()->finish();
    $cli->getProgress()->clear();

	// See how long it took to run everything
	$timing = $cli->timing();

	// Output and we're done!
	output(PHP_EOL."<info>-------></info> Finished in $timing <info><-------</info>");

})->descriptions('Installs all enterprise packages in the local ProcessMaker core (processmaker/processmaker) codebase.', [
		'--for_41_develop' => 'Uses 4.1 version of the supported packages'
]);

$app->command('packages', function () {
	Packages::outputPackagesTable();
});

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

$app->command('clone-all [-f|--force]', function ($force = null) {
	try {
        if (Packages::cloneAllPackages($force)) {
            info('All ProcessMaker packages cloned successfully!');
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
