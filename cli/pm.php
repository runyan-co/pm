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
use Symfony\Component\Console\Question\Question;

use function ProcessMaker\Cli\ {
    info, table, output, resolve, warning, warningThenExit,
};

Container::setInstance(new Container);
Container::getInstance()->singleton(\ProcessMaker\Cli\CommandLine::class);

$app = new Application('ProcessMaker CLI Tool', '0.5.0');

/*
 * -------------------------------------------------+
 * |                                                |
 * |    Command: Ci:Install Packages                |
 * |                                                |
 * -------------------------------------------------+
 */
$app->command('ci:install-packages', function() {
    PackagesCi::install();
});

if (!FileSystem::isDir(PM_HOME_PATH)) {
    /*
	 * -------------------------------------------------+
	 * |                                                |
	 * |    Command: Install                            |
	 * |                                                |
	 * -------------------------------------------------+
	 */
    $app->command('install', function (InputInterface $input, OutputInterface $output) {

        // First thing we want to do is ask the user to
        // tell us the absolute path to the core codebase
        $helper = $this->getHelperSet()->get('question');

		// Callback to autocomplete the directories available
	    // as the user is typing
        $callback = function (string $userInput): array {

            $inputPath = preg_replace('%(/|^)[^/]*$%', '$1', $userInput);
            $inputPath = '' === $inputPath ? '.' : $inputPath;
            $foundFilesAndDirs = @scandir($inputPath) ?: [];

            return array_map(static function ($dirOrFile) use ($inputPath) {
                return $inputPath.$dirOrFile;
            }, $foundFilesAndDirs);
        };

        $question = "<info>Please enter the absolute path to the local copy of the processmaker/processmaker codebase</info>:".PHP_EOL;
        $question = new Question($question);
		$question->setAutocompleterCallback($callback);
        $codebase_path = $helper->ask($input, $output, $question);

        // Make sure they entered something
        if (null === $codebase_path) {
            warningThenExit('You must enter a valid absolute path to continue the installation. Please try again.');
        }

        // Check for composer.json
        if (!FileSystem::exists("$codebase_path/composer.json")) {
            warningThenExit("Could not the composer.json for processmaker/processmaker in: $codebase_path");
        }

        // Next we need to know where all of the local copies
        // of the processmaker/* packages will be stored
        $question = "<info>Please enter the absolute path to the directory where local copies of the ProcessMaker packages will be stored:</info>:".PHP_EOL;
        $question = new Question($question);
        $question->setAutocompleterCallback($callback);
		$packages_path = $helper->ask($input, $output, $question);

        // Make sure they entered something
        if (null === $packages_path) {
            warningThenExit('You must enter a valid absolute path to continue the installation. Please try again.');
        }

		// Creates the sudoers entry and the base config file/directory
        Install::install($codebase_path, $packages_path);

		// Clone down all of the supported packages if the
	    // packages directory is empty
	    if (count(FileSystem::scandir($packages_path)) === 0) {
			$this->runCommand('packages:clone-all');
	    }

		info('Installation complete!');

    })->descriptions('Runs the installation process for this tool. Necessary before other commands will appear.');

} else {

    /*
	 * -------------------------------------------------+
	 * |                                                |
	 * |    Command: Supervisor:Status                  |
	 * |                                                |
	 * -------------------------------------------------+
	 */
    $app->command('supervisor:status', function () {
        info(Supervisor::running()
            ? 'Supervisor is running'
            : 'Supervisor is not running or available');
    })->descriptions('Get supervisor\'s running status');

    /*
	 * -------------------------------------------------+
	 * |                                                |
	 * |    Command: Supervisor:Stop                    |
	 * |                                                |
	 * -------------------------------------------------+
	 */
    $app->command('supervisor:stop [process]', function ($process = null) {
        try {
            if (Supervisor::stop($process)) {
                info('Supervisor process(es) successfully stopped.');
            } else {
                warning("Process(es) were already stopped or an error occurred while attempting to stop them.");
            }
        } catch (RuntimeException $exception) {
            output("<fg=red>Problem stopping process(es): </>".PHP_EOL.$exception->getMessage());
        }
    })->descriptions('Attempt to stop the supervisor process by name if available, otherwise it stops all processes', [
		'process' => 'The name of the supervisor process to start or restart'
    ]);

    /*
	 * -------------------------------------------------+
	 * |                                                |
	 * |    Command: Supervisor:Restart                 |
	 * |                                                |
	 * -------------------------------------------------+
	 */
	    $app->command('supervisor:restart [process]', function ($process = null) {
	       try {
			   if (Supervisor::restart($process)) {
				   info('Supervisor process(es) successfully restarted.');
			   } else {
                   warning("Process(es) could not be restarted.");
			   }
	       } catch (RuntimeException $exception) {
               output("<fg=red>Problem restarting process(es): </>".PHP_EOL.$exception->getMessage());
	       }
	    })->descriptions('Attempt to start/restart the supervisor process by name if available, otherwise it restarts all processes', [
            'process' => 'The name of the supervisor process to start or restart'
        ]);

    /*
	* -------------------------------------------------+
	* |                                                |
	* |    Command: Core:Reset                         |
	* |                                                |
	* -------------------------------------------------+
	*/
	$app->command('core:reset [branch] [-d|--bounce-database', function (InputInterface $input, OutputInterface $output) {

		$branch = $input->getArgument('branch') ?? 'develop';
		$verbose = $input->getOption('verbose') ?? false;

		// Whether or not we should destroy the MySQL database and then recreate it
		$bounce_database = $input->getOption('bounce-database') ?? false;

		$helper = $this->getHelperSet()->get('question');
		$question = new ConfirmationQuestion('<comment>Warning:</comment> This will remove all changes to the core codebase and reset the database. Continue? <info>(yes/no)</info> ', false);

		if (false === $helper->ask($input, $output, $question)) {
			warningThenExit('Reset aborted.');
		}

        // Put together the commands necessary
        // to reset the core codebase
        $command_set = Reset::buildResetCommands($branch, $bounce_database);

        // Grab an instance of the CommandLine class
        $cli = resolve(\ProcessMaker\Cli\CommandLine::class);

        // Count up the total number of steps in the reset process
        $steps = collect($command_set)->flatten()->count();

        // We also need to add an additional step to account for
        // the step before last of reformatting the .env file which
        // is done through a class rather than a command execution
		++$steps;

		// If the .env file exists before resetting, we have to
		// remove it to allow the artisan install process to work
        if ($env_file_exists = FileSystem::exists($env = Config::codebasePath().'/.env')) {
			++$steps;
        }

        // The steps increase by 2, for example, if supervisor
        // is running since we need to stop it before executing
        // the commands, then restart it when were finished
        if ($supervisor_should_restart = Supervisor::running()) {
            $steps += 2;
        }

        // Add a progress bar
        $cli->createProgressBar($steps, 'message');
        $cli->getProgress()->setMessage('Starting install...');
        $cli->getProgress()->start();

		// First, let's stop any supervisor processes
		// to prevent them from throwing exceptions
		// or causing other chaos while we do the reset
		if ($supervisor_should_restart) {
            $cli->getProgress()->setMessage('Stopping supervisor processes...');
			Supervisor::stop();
            $cli->getProgress()->advance();
		}

		// The 'git clean' command run doesn't affect ignored
		// files and since .env file is ignored we need to remove
		// it in advance a // you cannot run the ProcessMaker
		// artisan install command with it present
		if ($env_file_exists) {
            $cli->getProgress()->setMessage('Removing .env file...');
			FileSystem::unlink($env);
            $cli->getProgress()->advance();
		}

		// Iterate through them and execute
		foreach ($command_set as $type_of_commands => $commands) {
			$cli->getProgress()->setMessage("Running $type_of_commands commands...");

			foreach ($commands as $command) {
                try {
                    $out = $cli->run($command, static function ($exitCode, $out) {
                        throw new RuntimeException($out);
                    }, Config::codebasePath());
                } catch (RuntimeException $exception) {

                    $cli->getProgress()->clear();

                    output("<fg=red>Command Failed:</> $command");
                    output($exception->getMessage());

                    exit(0);
                }

                $cli->getProgress()->advance();

                if (!$verbose) {
                    continue;
                }

                $cli->getProgress()->clear();

                output("<info>Command Successful:</info> $command");
                output($out);

                $cli->getProgress()->display();
			}
		}

		// Now we need to reformat the .env file so it's
		// setup properly for a local environment
        $cli->getProgress()->setMessage('Reformatting .env file...');
		Reset::formatEnvFile();
        $cli->getProgress()->advance();

        // If supervisor processes were stopped before
        // executing the commands, now we can restart them
        if ($supervisor_should_restart) {
            $cli->getProgress()->setMessage('Restarting supervisor processes...');
            Supervisor::restart();
            $cli->getProgress()->advance();
        }

		$cli->getProgress()->finish();
        $cli->getProgress()->clear();

        // See how long it took to run everything
        $timing = $cli->timing();

        // Output and we're done!
        output(PHP_EOL."<info>Finished in</info> $timing");
	})->descriptions('Reset the core codebase, install composer and npm dependencies, builds npm assets', [
		'branch' => 'Default: \'develop\'. Otherwise will try to switch to the branch name provided.',
		'--bounce-database' => 'Drops the database the core codebase was using and recreates it'
	]);

    /*
	 * -------------------------------------------------+
	 * |                                                |
	 * |    Command: Core:Install Packages              |
	 * |                                                |
	 * -------------------------------------------------+
	 */
    $app->command('core:install-packages [-4|--for_41_develop]', function (InputInterface $input, OutputInterface $output) {

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
                warningThenExit('Packages install aborted.');
            }

            // Re-run the install but add the force argument to
            // prevent the incompatible exception from being thrown
            $install_commands = $build_install_commands(true);
        }

        // Grab an instance of the CommandLine class
        $cli = resolve(\ProcessMaker\Cli\CommandLine::class);

        // Count up the total number of steps
        $steps = $install_commands->flatten()->count();

        // The steps increase by 2, for example, if supervisor
        // is running since we need to stop it before executing
        // the commands, then restart it when were finished
        if ($supervisor_should_restart = Supervisor::running()) {
            $steps += 2;
        }

        // Add a progress bar
        $cli->createProgressBar($steps, 'message');
        $cli->getProgress()->setMessage('Starting install...');
        $cli->getProgress()->start();

        // First, let's stop any supervisor processes
        // to prevent them from throwing exceptions
        // or causing other chaos while we do the reset
        if ($supervisor_should_restart) {
            $cli->getProgress()->setMessage('Stopping supervisor processes...');
            Supervisor::stop();
            $cli->getProgress()->advance();
        }

        // Iterate through the collection of commands
        foreach ($install_commands as $package => $command_collection) {

            // Iterate through each command and attempt to run it
            foreach ($command_collection as $command) {

				// todo Clean this up as checking for the type of command like this is not ideal
				$message = $package === 'horizon'
					? 'Restarting horizon...'
					: "Installing $package...";

                // Update the progress bar
                $cli->getProgress()->setMessage($message);

                try {
                    $command_output = $cli->run($command, static function ($exitCode, $out) {
                        throw new RuntimeException($out);
                    }, Config::codebasePath());
                } catch (RuntimeException $exception) {

                    $cli->getProgress()->clear();

                    output("<fg=red>Command Failed:</> $command");
                    output($exception->getMessage());

                    $cli->getProgress()->display();

                    continue;
                }

                $cli->getProgress()->advance();

                if (!$verbose) {
                    continue;
                }

                // If the user wants verbose output, then show the
                // stdout for all of the successful commands as well
                // (in addition to the ones which failed)
                $cli->getProgress()->clear();

                output("<info>Command Success:</info> $command");
                output($command_output);

                $cli->getProgress()->display();
            }
        }

		// Restart supervisor processes
        if ($supervisor_should_restart) {
            $cli->getProgress()->setMessage('Restarting supervisor processes...');
            Supervisor::restart();
            $cli->getProgress()->advance();
        }

        // Clean up the progress bar
        $cli->getProgress()->finish();
        $cli->getProgress()->clear();

        // See how long it took to run everything
        $timing = $cli->timing();

        // Output and we're done!
        output(PHP_EOL."<info>Finished in</info> $timing");

    })->descriptions('Installs all enterprise packages in the local ProcessMaker core (processmaker/processmaker) codebase.', [
        '--for_41_develop' => 'Uses 4.1 version of the supported packages'
    ]);

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Packages:Status                    |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('packages:status', function () {

        table(['Name', 'Version', 'Branch', 'Commit Hash'], Packages::getPackagesTableData());

    })->descriptions('Display the current version, branch, and names of known local packages');

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Packages:Pull                      |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('packages:pull [-4|--for_41_develop]', function (InputInterface $input, OutputInterface $output) {

        // Updates to 4.1-branch of packages (or not)
        $for_41_develop = $input->getOption('for_41_develop');

        // Set verbosity level of output
        $verbose = $input->getOption('verbose');

        // Put everything together and run it
        $for_41_develop ? Packages::pull41($verbose) : Packages::pull($verbose);

    })->descriptions('Resets and updates the locally stored ProcessMaker 4 composer packages to the latest from GitHub.',
        ['--for_41_develop' => 'Change each package to the correct version for the 4.1 version of processmaker/processmaker']
    );

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Packages:Clone All                 |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('packages:clone-all [-f|--force]', function ($force = null) {
        foreach (Packages::getSupportedPackages() as $index => $package) {
            try {
                if (Packages::clonePackage($package)) {
                    info("Package $package cloned successfully!");
                }
            } catch (Exception $exception) {
                warning($exception->getMessage());
            }
        }
    })->descriptions('Clone all supported ProcessMaker 4 packages to a local directory', [
        '--force' => 'Delete the package locally if it exists already'
    ]);
}

$app->run();
