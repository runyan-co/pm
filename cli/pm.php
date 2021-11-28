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
use Illuminate\Support\Collection;

use function ProcessMaker\Cli\table;
use function ProcessMaker\Cli\info;

Container::setInstance(new Container);

$app = new Application('ProcessMaker CLI Tool', '0.5.0');

$app->command('test', function () {

	$for_41_develop = false;

	$git_branch = $for_41_develop
		? '4.1-develop'
		: "$(git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@')";

	$package_commands = [
        'git reset --hard',
        "git checkout $git_branch",
        'git fetch --all',
        'git pull --force',
    ];

	$result = [];

	foreach (Packages::getPackages() ?? [] as $package) {
		if (!array_key_exists($package['name'], $result)) {
            $result[$package['name']] = [];
		}

		$package_set = &$result[$package['name']];

		$package_set['version'] = Packages::getPackageVersion($package['path']);

		$package_set['path'] = $package['path'];

		$package_set['commands'] = array_map(function ($command) use ($package) {
			return 'cd '.$package['path'].' && '.$command;
		}, $package_commands);
	}

	$commands = collect($result)->transform(function (array $set) {
		return $set['commands'];
	})->toArray();

	ProcessManager::buildProcessesBundleAndStart($commands);
});

$app->command('trust [--off]', function ($off) {
	if ($off) {
        ProcessMaker::unlinkFromUsersBin();
        ProcessMaker::removeSudoersEntry();

        return info('ProcessMaker CLI tool removed sudoers file.');
	} else {
		ProcessMaker::symlinkToUsersBin();
        ProcessMaker::createSudoersEntry();

        info('ProcessMaker CLI tool added to sudoers file.');
	}
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
