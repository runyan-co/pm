<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Illuminate\Container\Container;
use ProcessMaker\Cli\Facades\Config;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

if (!defined('HOMEBREW_PREFIX')) {
    define('HOMEBREW_PREFIX', exec('printf $(brew --prefix)'));
}

if (!defined('PM_HOME_PATH')) {
    define('PM_HOME_PATH', $_SERVER['HOME'] . '/.config/pm');
}

if (!defined('USER_HOME')) {
    define('USER_HOME', ($home = getenv('HOME')) ? $home : null ?? exec('printf $(cd ~ && pwd)'));
}

/**
 * @param  string  $output
 * @param  int  $exitCode
 *
 * @return string
 */
function warning_then_exit(string $output, int $exitCode = 0): string {
    return (warning($output) . exit($exitCode));
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
 * @param  string|null  $package_name
 *
 * @return mixed
 */
function packages_path(?string $package_name = null) {
    return Config::packagesPath($package_name);
}

/**
 * @param  string|null  $log_file_name
 *
 * @return mixed
 */
function logs_path(?string $log_file_name = null) {
    return Config::systemLogsPath($log_file_name);
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
