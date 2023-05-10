<?php

declare(strict_types=1);

define('MICROTIME_START', microtime(true));

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
} elseif (file_exists(__DIR__.'/../../../autoload.php')) {
    require __DIR__.'/../../../autoload.php';
} else {
    require USER_HOME.'/.composer/vendor/autoload.php';
}

use \Illuminate\Container\Container;

Container::setInstance($container = new Container);

Container::getInstance()
    ->when(\ProcessMaker\Cli\Application::class)
    ->needs('$version')
    ->give('2.0.1');

$app = \ProcessMaker\Cli\Facades\Application::getInstance();

return $app;
