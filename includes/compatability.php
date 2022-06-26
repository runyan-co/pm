<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    return;
}

/**
 * Check the system's compatibility with the pm.
 */
$inTestingEnvironment = strpos($_SERVER['SCRIPT_NAME'], 'phpunit') !== false;

if (PHP_OS !== 'Darwin' && ! $inTestingEnvironment) {
    echo 'pm only supports the macOS operating system.'.PHP_EOL;

    exit(1);
}

if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    echo 'pm requires PHP 7.2 or later.';

    exit(1);
}

if (exec('which brew') == '' && ! $inTestingEnvironment) {
    echo 'pm requires Homebrew to be installed on your Mac.';

    exit(1);
}
