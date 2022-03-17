<?php

namespace ProcessMaker\Cli;

use RuntimeException;
use Illuminate\Support\Str;

class Environment
{
    public const NODE_VERSION = '14.4.0';

    public const NPM_VERSION = '6.14.5';

    public const PHP_EXTENSIONS = [
        'GD' => 'https://www.php.net/manual/en/image.installation.php',
        'imagick' => 'https://www.php.net/manual/en/book.imagick.php',
        'imap' => 'https://www.php.net/manual/en/imap.setup.php'
    ];

    protected CommandLine $cli;

    /**
     * @param  \ProcessMaker\Cli\CommandLine  $cli
     */
    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    /**
     * Checks required PHP extensions
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function checkPhpExtensions()
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
    public function checkNodeVersion()
    {
        $version = $this->cli->runCommand('node -v', static function ($exitCode, $output) {
            throw new RuntimeException($output);
        });

        if (($version = Str::remove([PHP_EOL, 'v'], $version)) !== self::NODE_VERSION) {
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
    public function checkNpmVersion()
    {
        $version = $this->cli->runCommand('npm -v', static function ($exitCode, $output) {
            throw new RuntimeException($output);
        });

        if (($version = Str::remove([PHP_EOL, 'v'], $version)) !== self::NPM_VERSION) {
            throw new RuntimeException("Npm version check failed. Found version {$version} but ".self::NPM_VERSION." is required.");
        }
    }
}
