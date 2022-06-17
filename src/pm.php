#!/usr/bin/env php
<?php

declare(strict_types=1);

define('MICROTIME_START', microtime(true));

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
} elseif (file_exists(__DIR__.'/../../../autoload.php')) {
    require __DIR__.'/../../../autoload.php';
} else {
    require getenv('HOME').'/.composer/vendor/autoload.php';
}

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use ProcessMaker\Cli\Application;
use ProcessMaker\Cli\Facades\Core;
use ProcessMaker\Cli\Facades\CommandLine;
use ProcessMaker\Cli\Facades\Environment;
use ProcessMaker\Cli\Facades\FileSystem;
use ProcessMaker\Cli\Facades\Logs;
use ProcessMaker\Cli\Facades\Install;
use ProcessMaker\Cli\Facades\Packages;
use ProcessMaker\Cli\Facades\PackagesCi;
use ProcessMaker\Cli\Facades\ParallelRun;
use ProcessMaker\Cli\Facades\Reset;
use ProcessMaker\Cli\Facades\SnapshotsRepository as Snapshots;
use ProcessMaker\Cli\Facades\Supervisor;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use function ProcessMaker\Cli\resolve;
use function ProcessMaker\Cli\codebase_path;
use function ProcessMaker\Cli\info;
use function ProcessMaker\Cli\output;
use function ProcessMaker\Cli\table;
use function ProcessMaker\Cli\warning;
use function ProcessMaker\Cli\warning_then_exit;

Container::setInstance($container = new Container);

$app = new Application('ProcessMaker CLI Tool', '1.1.6');

$app->useContainer($container);

/*
 * -------------------------------------------------+
 * |                                                |
 * |    Command: env-check                          |
 * |                                                |
 * -------------------------------------------------+
 */
$app->command('env-check', function (): void {
	$success = true;

    foreach (Environment::environmentChecks() as $result) {
		if ($result instanceof RuntimeException) {
            $success = output("<fg=red>Environment check failed with message:</> {$result->getMessage()}") ?? false;
		}
    }

	if ($success) {
		info('Environment checks successful!');
	}
})->descriptions('Check for the correct version of node, npm, and the proper php extensions.');

/*
 * -------------------------------------------------+
 * |                                                |
 * |    Command: Ci:Install Packages                |
 * |                                                |
 * -------------------------------------------------+
 */
$app->command('ci:install-packages', function (): void {
    PackagesCi::install();
})->descriptions('Intended to use with CircleCi to install necessary enterprise packages for testing.');

if (!is_dir(PM_HOME_PATH)) {

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Install                            |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('install', function (InputInterface $input, OutputInterface $output): void {

        // First thing we want to do is ask the user to
        // tell us the absolute path to the core codebase
        $helper = $this->getHelperSet()->get('question');
		$question = new ConfirmationQuestion('<comment>One or more environment checks failed. Continue with installation?</comment> (yes/no) ');
        $success = true;

		info('Running environment checks...');

		// Run a set of environment checks and print relevant info when one fails
        foreach (Environment::environmentChecks() as $result) {
            if ($result instanceof RuntimeException) {
                $success = output("<fg=red>Environment check failed with message:</> {$result->getMessage()}");
            }
        }

		// If the environment check failed, ask if we should continue anyway
		if (!$success && !$helper->ask($input, $output, $question)) {
			exit(0);
		}

		// A little explanation
	    output(PHP_EOL.'You\'ll only need to run this install process one time. All of the values will be saved to ~/.config/pm/config.json.'.PHP_EOL);

        // Callback to autocomplete the directories available
        // as the user is typing
        $filesystem_callback = static function (string $userInput): array {
            $inputPath = preg_replace('%(/|^)[^/]*$%', '$1', $userInput);
            $inputPath = $inputPath === '' ? '.' : $inputPath;
            $foundFilesAndDirs = @scandir($inputPath) ?: [];

            return array_map(static function ($dirOrFile) use ($inputPath) {
                return $inputPath.$dirOrFile;
            }, $foundFilesAndDirs);
        };

		// Represents current step the config setup is on
		$current = 0;

		// Iterate through the defaults to build the config.json contents
		foreach ($configuration = \ProcessMaker\Cli\Config::$defaults as $config_key => $config) {

			// Keep track of where were at for the user's sake
            $current += 1;
            $count = count($configuration);

			// Setup the basic info we'll need
			$config = (object) $config;
			$description = strtolower($config->description);
			$default = !blank($config->default) ? $config->default : null;
			$question = "<comment>Step ${current} of ${count}</comment>".PHP_EOL."<info>Please enter the {$description}:</info>";

			if ($default) {
				$question .= " (defaults to: {$default})";
			}

			// Setup the question to ask the user for the config value
            $question = new Question($question.PHP_EOL, $default);

			// Setup the normalizer to ensure we have no extra spaces
            $question->setNormalizer(static function ($value) {
                return $value ? trim($value) : '';
            });

			switch ($config_key) {
				case 'codebase_path':
                    $question->setAutocompleterCallback($filesystem_callback);
                    $question->setValidator(static function ($value) {
                        // Make sure they entered something
						if (blank($value)) {
							throw new RuntimeException('Please enter a valid absolute path to continue the installation.');
						}

                        // Check for composer.json
                        if (!FileSystem::exists("${value}/composer.json")) {
                            throw new RuntimeException("Please try again: Could not find the composer.json for processmaker/processmaker in: ${value}");
                        }

						return $value;
                    });

                    break;

				case 'packages_path':
                    $question->setAutocompleterCallback($filesystem_callback);
                    $question->setValidator(static function ($value) {
                        // Make sure they entered something
                        if (blank($value)) {
                            throw new RuntimeException('Please enter a valid absolute path to continue the installation.');
                        }

						return $value;
                    });

                    break;

				case 'url':
                    $question->setValidator(static function ($value) {
                        // Make sure they entered something
                        if (blank($value)) {
                            throw new RuntimeException('Please enter a valid url (e.g. http://192.168.86.20 or http://processmaker.test).');
                        }

						return $value;
                    });

					break;
            }

			// Actually ask the question and receive the input
            $value = $helper->ask($input, $output, $question);

            // Set the value with the value
            $configuration[$config_key] = $value;
		}

        // Creates the sudoers entry and the base config file/directory
        Install::install($configuration);

        info('Installation complete!');

    })->descriptions('Runs the installation process for this tool. Necessary before other commands will appear.');
} else {

    /*
	 * -------------------------------------------------+
	 * |                                                |
	 * |    Command: logs:tail                          |
	 * |                                                |
	 * -------------------------------------------------+
	 */
    $app->command('logs:tail', function (InputInterface $input, OutputInterface $output) {

        $helper = $this->getHelperSet()->get('question');

        $question = new ChoiceQuestion('<comment>Please select the log file to view:</comment>',
            Logs::getApplicationLogs()
        );

        $selected_log_file = $helper->ask($input, $output, $question);
        $selected_log_file = codebase_path("storage/logs/{$selected_log_file}");

		output(PHP_EOL.'<comment>Type Ctrl+C to exit...</comment>'.PHP_EOL);

        CommandLine::passthru("tail -f -n 20 {$selected_log_file}");

    })->descriptions('Displays live feed of selected application log file');

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Supervisor:Status                  |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('supervisor:status', function (): void {
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
    $app->command('supervisor:stop [process]', function ($process = null): void {
        try {
            if (Supervisor::stop($process)) {
                info('Supervisor process(es) successfully stopped.');
            } else {
                warning('Process(es) were already stopped or an error occurred while attempting to stop them.');
            }
        } catch (RuntimeException $exception) {
            output('<fg=red>Problem stopping process(es): </>'.PHP_EOL.$exception->getMessage());
        }
    })->descriptions('Attempt to stop the supervisor process by name if available, otherwise it stops all processes', [
        'process' => 'The name of the supervisor process to start or restart',
    ]);

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Supervisor:Restart                 |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('supervisor:restart [process]', function ($process = null): void {
        try {
            if (Supervisor::restart($process)) {
                info('Supervisor process(es) successfully restarted.');
            } else {
                warning('Process(es) could not be restarted.');
            }
        } catch (RuntimeException $exception) {
            output('<fg=red>Problem restarting process(es):</>'.PHP_EOL.$exception->getMessage());
        }
    })->descriptions('Attempt to start/restart the supervisor process by name if available, otherwise it restarts all processes', [
            'process' => 'The name of the supervisor process to start or restart',
        ]);

    /*
	* -------------------------------------------------+
	* |                                                |
	* |    Command: Core:Both                          |
	* |                                                |
	* -------------------------------------------------+
	*/
    $app->command('core:both [branch] [-4|--for_41_develop] [-d|--bounce-database] [--no-npm] [-y|--yes] [--except=]',
        function (InputInterface $input, OutputInterface $output) use ($app): void {

		// This command (core:both) basically just let's us run both the
		// core:reset command followed by the core:install-packages
		// command to make it easier
		$command = static function ($command) use ($input) {
			// core:reset command options/arguments
			if (Str::contains($command, 'core:reset')) {
                if ($branch = $input->getArgument('branch') ?? 'develop') {
                    $command .= " {$branch}";
                }

                if ($input->getOption('no-npm')) {
                    $command .= ' --no-npm';
                }

                if ($input->getOption('bounce-database')) {
                    $command .= ' -d';
                }

                if ($input->getOption('yes')) {
                    $command .= ' -y';
                }
			}

            if ($input->getOption('verbose')) {
                $command .= ' -v';
            }

			// core:install-packages command options/arguments
			if (Str::contains($command, 'core:install-packages')) {
				if ($input->getArgument('branch') === '4.1-develop' || $input->getOption('for_41_develop')) {
					$command .= ' -4';
				}

                if ($except_packages = $input->getOption('except')) {
                    $command .= " --except {$except_packages}";
                }
            }

			return $command;
		};

		if ($app->runCommand($command('core:reset'), $output) === 0) {
            $app->runCommand($command('core:install-packages'), $output);
		}
	})->descriptions('Runs both the core:reset and core:install-packages commands');

    /*
    * -------------------------------------------------+
    * |                                                |
    * |    Command: Core:Reset                         |
    * |                                                |
    * -------------------------------------------------+
    */
    $app->command('core:reset [branch] [-d|--bounce-database] [--no-npm] [-y|--yes]',
        function (InputInterface $input, OutputInterface $output): void {

            // Grab an instance of the CommandLine class
            $cli = CommandLine::getInstance();

            $branch = $input->getArgument('branch') ?? 'develop';
            $verbose = $input->getOption('verbose') ?? false;
            $no_npm = $input->getOption('no-npm') ?? false;
            $no_confirmation = $input->getOption('yes') ?? false;
            $bounce_database = $input->getOption('bounce-database') ?? false;
            $helper = $this->getHelperSet()->get('question');
            $question = new ConfirmationQuestion('<comment>Warning:</comment> This will remove all changes to the core codebase and reset the database. Continue? <info>(yes/no)</info> ', false);

            if (!$no_confirmation && $helper->ask($input, $output, $question) === false) {
                warning_then_exit('Reset aborted.');
            }

			// Since we completely remove the codebase directory when resetting,
	        // we need to make sure the current working directory is different
			if (getcwd() === codebase_path()) {
				warning_then_exit('This command cannot be executed directly in the codebase directly. Please change to the another directory and try again.');
			}

			info('Beginning install...');

            // Put together the commands necessary
            // to reset the core codebase
            $command_set = Reset::buildResetCommands($branch, $bounce_database);

            // Remove npm commands if needed
            if ($no_npm && array_key_exists('npm', $command_set)) {
                unset($command_set['npm']);
            }

            // Count up the total number of steps in the reset process
			$cli->createProgressBar((collect($command_set)->flatten()->count() - 1), 'message');

			$cli->getProgress()->setMessage('Installing core...');
            $cli->getProgress()->start();

			// Install processmaker/processmaker
			Core::clone();

			// If --no-npm was passed, then we need to inform the user
	        // they need to restart supervisor once the npm assets are
	        // installed so the echo server can function properly
	        if ($no_npm && Core::getInstance()::$shouldRestartSupervisor) {
                Core::getInstance()::$shouldRestartSupervisor = false;
	        }

            // Iterate through them and execute
            foreach ($command_set as $type_of_commands => $commands) {
                $cli->getProgress()->setMessage("Running ${type_of_commands} commands...");

                foreach ($commands as $command) {
                    try {
                        $out = $cli->run($command, static function ($exitCode, $out): void {
                            throw new RuntimeException($out);
                        }, codebase_path());
                    } catch (RuntimeException $exception) {
                        $cli->getProgress()->clear();

                        output("<fg=red>Command Failed:</> ${command}");
                        output($exception->getMessage());

                        exit(0);
                    }

                    if (! $verbose) {
                        continue;
                    }

                    $cli->getProgress()->clear();

                    output("<info>Command Successful:</info> ${command}");
                    output($out);

                    $cli->getProgress()->display();
                }

                $cli->getProgress()->advance();
            }

            // Now we need to reformat the .env file so it's
            // setup properly for a local environment
            $cli->getProgress()->setMessage('Reformatting .env file...');
            $cli->getProgress()->advance();

            Reset::formatEnvFile();

            // If supervisor processes were stopped before
            // executing the commands, now we can restart them
            if (!$no_npm && Core::getInstance()::$shouldRestartSupervisor) {
                $cli->getProgress()->setMessage('Restarting supervisor processes...');
                $cli->getProgress()->advance();

                Supervisor::restart();
            }

			if ($no_npm) {
				output(PHP_EOL.PHP_EOL."<comment>Important:</comment>");
				output("<comment>|---</comment> Since the --no-npm option was passed, the supervisor processes ".PHP_EOL.
					"<comment>|---</comment> need to be manually restarted (to make sure the echo server ".PHP_EOL.
					"<comment>|---</comment> doesn't throw an exception)");
			}

            $cli->getProgress()->finish();
            $cli->getProgress()->clear();

            // See how long it took to run everything
            $timing = Snapshots::getTimeElapsed();

            // Output and we're done!
            output(PHP_EOL."<info>Finished in</info> ${timing}");
        }
    )->descriptions('Reset the core codebase, install composer and npm dependencies, builds npm assets', [
		'branch' => 'Default: \'develop\'. Otherwise will try to switch to the branch name provided.',
		'--bounce-database' => 'Drop and recreate new database',
		'--no-npm' => 'Skip npm commands',
	]);

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Core:Install Packages              |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('core:install-packages [-4|--for_41_develop] [--except=]',
        function (InputInterface $input, OutputInterface $output): void {

            // Indicates if we should install the 4.1-develop
            // versions of each package or the 4.2
            $for_41_develop = $input->getOption('for_41_develop');

            // Should the output be verbose or not
            $verbose = $input->getOption('verbose');

			// If the --except option is provided, attempt to remove
	        // the package(s) from the packages to be installed
			if ($except_packages = $input->getOption('except')) {
				$except_packages = Str::contains($except_packages, ',')
                    ? explode(',', $except_packages)
                    : [$except_packages];
			}

			info('Setting up enterprise package installation...');

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
                if ($helper->ask($input, $output, $question) === false) {
                    warning_then_exit('Packages install aborted.');
                }

                // Re-run the install but add the force argument to
                // prevent the incompatible exception from being thrown
                $install_commands = $build_install_commands(true);
            }

			// If there are any packages to remove from the install
	        // list, go ahead and search for them as a key in the
	        // $install_command and remove them if they exist
	        if ($except_packages) {
				foreach ($except_packages as $package_name) {
					if($install_commands->has($package_name)) {
					   $install_commands->forget($package_name);
					}
				}
	        }

            // Grab an instance of the CommandLine class
            $cli = CommandLine::getInstance();

            // Count up the total number of steps
            $steps = $install_commands->flatten()->count();

            // The steps increase by 2, for example, if supervisor
            // is running since we need to stop it before executing
            // the commands, then restart it when were finished
            if (Core::getInstance()::$shouldRestartSupervisor = Supervisor::running()) {
                $steps += 2;
            }

            // Add a progress bar
            $cli->createProgressBar($steps, 'message');
            $cli->getProgress()->setMessage('Starting enterprise packages install...');
            $cli->getProgress()->start();

            // First, let's stop any supervisor processes
            // to prevent them from throwing exceptions
            // or causing other chaos while we do the reset
            if (Core::getInstance()::$shouldRestartSupervisor) {
                $cli->getProgress()->setMessage('Stopping supervisor processes...');
                $cli->getProgress()->advance();

                Supervisor::stop();
            }

            // Iterate through the collection of commands
            foreach ($install_commands as $package => $command_collection) {

                // Iterate through each command and attempt to run it
                foreach ($command_collection as $command) {

                // todo Clean this up as checking for the type of command like this is not ideal
                    $message = $package === 'horizon'
	                    ? 'Restarting horizon...'
	                    : "Installing ${package}...";

                    // Update the progress bar
                    $cli->getProgress()->setMessage($message);
                    $cli->getProgress()->advance();

                    try {
                        $command_output = $cli->run($command, static function ($exitCode, $out): void {
                            throw new RuntimeException($out);
                        }, codebase_path());
                    } catch (RuntimeException $exception) {
                        $cli->getProgress()->clear();

                        output("<fg=red>Command Failed:</> ${command}");
                        output($exception->getMessage());

                        $cli->getProgress()->display();

                        continue;
                    }

                    if (! $verbose) {
                        continue;
                    }

                    // If the user wants verbose output, then show the
                    // stdout for all of the successful commands as well
                    // (in addition to the ones which failed)
                    $cli->getProgress()->clear();

                    output("<info>Command Success:</info> ${command}");
                    output($command_output);

                    $cli->getProgress()->display();
                }
            }

            // Restart supervisor processes
            if (Core::getInstance()::$shouldRestartSupervisor) {
                $cli->getProgress()->setMessage('Restarting supervisor processes...');
                $cli->getProgress()->advance();

                Supervisor::restart();
            }

            // Clean up the progress bar
            $cli->getProgress()->finish();
            $cli->getProgress()->clear();

            // See how long it took to run everything
            $timing = Snapshots::getTimeElapsed();

            // Output and we're done!
            output(PHP_EOL."<info>Finished in</info> ${timing}");
        }
    )->descriptions('Installs all enterprise packages in the local ProcessMaker core (processmaker/processmaker) codebase.', [
            '--for_41_develop' => 'Uses 4.1 version of the supported packages',
	        '--except' => 'A comma-seperated list of enterprise packages to exclude from installation',
        ]);

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Packages:Status                    |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('packages:status', function (): void {

        table(['Name', 'Version', 'Branch', 'Commit Hash'], Packages::getPackagesTableData());

    })->descriptions('Display the current version, branch, and names of known local packages');

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Packages:Pull                      |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('packages:pull [-4|--for_41_develop]', function (InputInterface $input, OutputInterface $output): void {

        // Updates to 4.1-branch of packages (or not)
        $for_41_develop = $input->getOption('for_41_develop');

        // Set verbosity level of output
        $verbose = $input->getOption('verbose');

		// Grab an instance of the Packages class
		$packages = Packages::getInstance();

        // Build the commands for each package (keyed by package name)
        $commands = $packages->buildPullCommands($for_41_develop ? '4.1-develop' : 'develop');

        // Store the pre-pull metadata for each package
		$metadata = $packages->takePackagesSnapshot();

		// Grab an instance ParallelRun
        $parallelRun = ParallelRun::getInstance();

		// Set verbosity
		$parallelRun->setVerbosity($verbose);

        // Set a closure to be called when the final process
        // exits, then build the process queue and run
		$parallelRun->start($commands, function () use ($metadata, $packages) {

			// Package metadata to fill a cli table to
			// inform the user how the pull went
            $table = (object) [];

            // Build the table rows
            foreach ($packages->takePackagesSnapshot(true) as $package => $updated) {
                $table->$package = (object) array_merge($metadata[$package], $updated);
            }

            // Sort the columns in a more sensible way
            foreach ($table as $key => $row) {
                $table->$key = (object) [
                    'name' => $row->name,
                    'version' => $row->version,
                    'updated_version' => $row->updated_version,
                    'branch' => $row->branch,
                    'updated_branch' => $row->updated_branch,
                    'commit_hash' => $row->commit_hash,
                    'updated_commit_hash' => $row->updated_commit_hash,
                ];
            }

            // Add console styling
            foreach ($table as $key => $row) {
                // Highlight the package name
                $table->$key->name = "<fg=cyan>{$row->name}</>";

                // If the versions are the same, no updated occurred.
                // If they are different, let's make it easier to see.
                if ($row->version !== $row->updated_version) {
                    $table->$key->updated_version = "<info>{$row->updated_version}</info>";
                }

                // Do the same thing with branches, since we may
                // have switch to 4.1 or 4.2 during the pull, which
                // is set by the user by adding a flag to the command
                if ($row->branch !== $row->updated_branch) {
                    $table->$key->updated_branch = "<info>{$row->updated_branch}</info>";
                }

                // One more time to see if the commit hash has changed
                if ($row->commit_hash !== $row->updated_commit_hash) {
                    $table->$key->updated_commit_hash = "<info>{$row->updated_commit_hash}</info>";
                }
            }

            // Change the objects back into arrays
            foreach ($table as $key => $row) {
                $table->$key = (array) $row;
            }

            // Create the table columns
            $columns = ['Name', 'Version ->', '-> Version', 'Branch ->', '-> Branch', 'Hash ->', '-> Hash'];

            // Format our results in an easy-to-ready table
            table($columns, (array) $table);
		});

    })->descriptions(
        'Resets and updates the locally stored ProcessMaker 4 composer packages to the latest from GitHub.',
        ['--for_41_develop' => 'Change each package to the correct version for the 4.1 version of processmaker/processmaker']
    );

    /*
     * -------------------------------------------------+
     * |                                                |
     * |    Command: Packages:Clone All                 |
     * |                                                |
     * -------------------------------------------------+
     */
    $app->command('packages:clone-all [-f|--force]', function ($force = null): void {
        Packages::cloneAllPackages($force ?? false);
    })->descriptions('Clone all supported ProcessMaker 4 packages to a local directory', [
        '--force' => 'Delete the package locally if it exists already',
    ]);
}

$app->run();
