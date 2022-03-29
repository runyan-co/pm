<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Container\Container;
use ProcessMaker\Cli\Facades\CommandLine as Cli;
use ProcessMaker\Cli\Facades\Config;
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
function warning_then_exit(string $output, int $exitCode = 0): string {
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
 *
 * @throws \Exception
 */
function tmpdir($dir = null, $prefix = 'tmp_', $mode = 0700, $maxAttempts = 100) {

    if (is_null($dir)) {
        $dir = sys_get_temp_dir();
    }

    $dir = rtrim($dir, DIRECTORY_SEPARATOR);

    if (! is_dir($dir) || ! is_writable($dir)) {
        return false;
    }

    if (strpbrk($prefix, '\\/:*?"<>|') !== false) {
        return false;
    }

    $attempts = 0;

    do {
        $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, $prefix, random_int(100000, mt_getrandmax()));
    } while (! mkdir($path, $mode) && $attempts++ < $maxAttempts);

    return $path;
}

/**
 * @param  string|null  $filename
 *
 * @return string
 */
function pm_path(?string $filename = null) {
    return PM_HOME_PATH . ($filename ? DIRECTORY_SEPARATOR . $filename : '');
}

/**
 * @param  string|null  $filename
 *
 * @return mixed
 */
function codebase_path(?string $filename = null) {
    return Config::codebasePath($filename);
}

/**
 * Resolve the given class from the container.
 *
 * @return mixed
 *
 * @throws \Illuminate\Contracts\Container\BindingResolutionException
 */
function resolve(string $class) {
    return Container::getInstance()->make($class);
}

/**
 * Swap the given class implementation in the container.
 *
 * @param  mixed  $instance
 */
function swap(string $class, $instance): void {
    Container::getInstance()->instance($class, $instance);
}

/**
 * @return mixed
 */
function user() {
    return $_SERVER['SUDO_USER'] ?? $_SERVER['USER'];
}

/**
 * Output the given text to the console.
 */
function info(string $output): void {
    output('<info>'.$output.'</info>');
}

/**
 * Output the given text to the console.
 */
function warning(string $output): void {
    output('<fg=red>' . $output . '</>');
}

/**
 * Output a table to the console.
 *
 * @param array $headers
 * @param array $rows
 */
function table(array $headers = [], array $rows = []): void {
    $table = new Table(new ConsoleOutput());
    $table->setHeaders($headers)->setRows($rows);
    $table->render();
}

/**
 * Output the given text to the console.
 */
function output(string $output): void {
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') {
        return;
    }

    (new ConsoleOutput())->writeln($output);
}
