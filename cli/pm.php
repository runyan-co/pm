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
use Illuminate\Container\Container;

Container::setInstance(new Container);

$app = new Application('ProcessMaker CLI Tool', '0.5.0');

$app->command('help', function () {
	//
});

$app->run();
