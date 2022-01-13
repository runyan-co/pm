<?php

namespace ProcessMaker\Cli;

use RuntimeException;
use Illuminate\Container\Container;
use ProcessMaker\Facades\CommandLine as Cli;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

if (!defined('HOMEBREW_PREFIX')) {
    define('HOMEBREW_PREFIX', Cli::run('printf $(brew --prefix)'));
}

if (!defined('PM_HOME_PATH')) {
    define('PM_HOME_PATH', $_SERVER['HOME'] . '/.config/pm');
}

if (!defined('USER_HOME')) {
    define('USER_HOME', getenv('HOME'));
}

/**
 * @param  string  $output
 * @param  int  $exitCode
 *
 * @return string
 */
function warningThenExit(string $output, int $exitCode = 0): string {
    return warning($output) . exit($exitCode);
}

/**
 * Create a temporary directory
 *
 * @param $dir
 * @param $prefix
 * @param $mode
 * @param $maxAttempts
 *
 * @return false|string
 * @throws \Exception
 */
function tmpdir($dir = null, $prefix = 'tmp_', $mode = 0700, $maxAttempts = 100) {
    if (is_null($dir)) {
        $dir = sys_get_temp_dir();
    }

    $dir = rtrim($dir, DIRECTORY_SEPARATOR);

    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    if (strpbrk($prefix, '\\/:*?"<>|') !== false) {
        return false;
    }

    $attempts = 0;

    do {
        $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, \random_int(100000, mt_getrandmax()));
    } while (!mkdir($path, $mode) && $attempts++ < $maxAttempts);

    return $path;
}

/**
 * Resolve the given class from the container.
 *
 * @param  string  $class
 *
 * @return mixed
 * @throws \Illuminate\Contracts\Container\BindingResolutionException
 */
function resolve(string $class) {
    return Container::getInstance()->make($class);
}

/**
 * Swap the given class implementation in the container.
 *
 * @param  string  $class
 * @param  mixed  $instance
 *
 * @return void
 */
function swap(string $class, $instance) {
    Container::getInstance()->instance($class, $instance);
}

/**
 * @return mixed
 */
function user() {
    if (! isset($_SERVER['SUDO_USER'])) {
        return $_SERVER['USER'];
    }

    return $_SERVER['SUDO_USER'];
}

/**
 * Verify that the script is currently running as "sudo".
 *
 * @return void
 * @throws \Exception
 */
function should_be_sudo() {
    if (!isset($_SERVER['SUDO_USER'])) {
        throw new RuntimeException('This command must be run with sudo.');
    }
}

/**
 * Output the given text to the console.
 *
 * @param  string  $output
 *
 * @return void
 */
function info(string $output) {
    output('<info>'.$output.'</info>');
}

/**
 * Output the given text to the console.
 *
 * @param  string  $output
 *
 * @return void
 */
function warning(string $output) {
    output('<fg=red>' . $output . '</>');
}

/**
 * Output a table to the console.
 *
 * @param array $headers
 * @param array $rows
 * @return void
 */
function table(array $headers = [], array $rows = [])  {
    $table = new Table(new ConsoleOutput);
    $table->setHeaders($headers)->setRows($rows);
    $table->render();
}

/**
 * Output the given text to the console.
 *
 * @param  string  $output
 *
 * @return void
 */
function output(string $output) {
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') {
        return;
    }

    (new ConsoleOutput())->writeln($output);
}
