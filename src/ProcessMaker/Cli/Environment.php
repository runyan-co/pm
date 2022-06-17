<?php

namespace ProcessMaker\Cli;

use Generator;
use RuntimeException;
use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\CommandLine as Cli;

class Environment
{
    public const NODE_VERSION = '16';

    public const NPM_VERSION = '8';

    public const PHP_EXTENSIONS = [
        'GD' => 'https://www.php.net/manual/en/image.installation.php',
        'imagick' => 'https://www.php.net/manual/en/book.imagick.php',
        'imap' => 'https://www.php.net/manual/en/imap.setup.php'
    ];

    public const EXECUTABLES = [
        'composer' => '"composer" not found. You may install it via homebrew using `brew install composer`',
        'node' => '"node" not found. You may install via nvm, which you can install through homebrew: `brew install nvm` then `nvm install 14.4.6`',
        'docker' => '"docker" not found. You can install via their website https://www.docker.com/products/docker-desktop/ or via homebrew `brew install --cask docker`',
        'timeout' => '"timeout" executable not found. You may need to install via homebrew using `brew install coreutils`',
    ];

    protected static $availableChecks = [
        'checkExecutables', 'checkPhpExtensions', 'checkNodeVersion', 'checkNpmVersion'
    ];

    /**
     * Returns an iterable with each value being one of the
     * environment checks being run, each of which either
     * return void or a caught RuntimeException.
     *
     * @return Generator
     *
     * @throws \RuntimeException
     */
    public function environmentChecks(): Generator
    {
        foreach (self::$availableChecks as $method) {
            try {
                yield $this->$method();
            } catch (RuntimeException $exception) {
                yield $exception;
            }
        }
    }

    /**
     * Check for required executables
     *
     * @return void
     */
    public function checkExecutables(): void
    {
        foreach (self::EXECUTABLES as $executable => $message) {
            Cli::run("which {$executable}", static function ($errorCode, $output) use ($message) {
                throw new RuntimeException($message);
            });
        }
    }

    /**
     * Checks required PHP extensions
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function checkPhpExtensions(): void
    {
        foreach (self::PHP_EXTENSIONS as $extension => $url) {
            if (!extension_loaded($extension)) {
                throw new RuntimeException("PHP {$extension} not loaded/installed. See: {$url}");
            }
        }
    }

    /**
     * Validates current running version of node
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function checkNodeVersion(): void
    {
        $version = Cli::runCommand('node -v', static function ($exitCode, $output) {
            throw new RuntimeException($output);
        });

        if (!Str::startsWith(Str::remove([PHP_EOL, 'v'], $version), self::NODE_VERSION)) {
            throw new RuntimeException("Node version check failed. Found version {$version} but ".self::NODE_VERSION." is required.");
        }
    }

    /**
     * Validates current running version of npm
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function checkNpmVersion(): void
    {
        $version = Cli::runCommand('npm -v', static function ($exitCode, $output) {
            throw new RuntimeException($output);
        });

        if (!Str::startsWith(Str::remove([PHP_EOL, 'v'], $version), self::NPM_VERSION)) {
            throw new RuntimeException("Npm version check failed. Found version {$version} but ".self::NPM_VERSION." is required.");
        }
    }
}
