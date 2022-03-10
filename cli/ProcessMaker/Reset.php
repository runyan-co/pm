<?php

namespace ProcessMaker\Cli;

use DomainException;
use ProcessMaker\Facades\CommandLine as Cli;
use ProcessMaker\Facades\Config;
use Illuminate\Support\Str;

class Reset
{
    protected CommandLine $cli;

    protected FileSystem $files;

    protected string $branch = '';

    protected static array $gitCommands = [
        'git checkout {branch}',
    ];

    protected static array $composerCommands = [
        COMPOSER_BINARY.' install --optimize-autoloader --no-interaction --no-progress'
    ];

    protected static array $npmCommands = [
        'npm install --non-interactive --quiet',
        'npm run dev --non-interactive --quiet',
    ];

    public function __construct(CommandLine $cli, FileSystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * Build a keyed array of arrays, each of which contains a subset of commands to
     * reset and setup the basics of the core (processmaker/processmaker) codebase
     *
     * @param  string  $branch
     * @param  bool  $bounce_database
     *
     * @return array
     */
    public function buildResetCommands(string $branch, bool $bounce_database = false): array
    {
        $this->branch = $branch;

        $gitCommands = ['git' => array_map(static function ($command) use ($branch) {
            return Str::replace('{branch}', $branch, $command);
        }, static::$gitCommands)];

        $databaseCommands = $bounce_database
            ? ['database' => [$this->buildDropAndCreateSqlCommand()]]
            : [];

        $dockerExecutable = Cli::findExecutable('docker');

        // todo Need to update the core repo to set the docker executable location as an arg for the artisan install command
        $formatEnvExampleCommand = [];

        if ($this->branch === '4.1-develop') {
            $formatEnvExampleCommand = [
                '4.1 cleanup' => [
                    "sed -i -- 's+/usr/bin/docker+$dockerExecutable+g' config/app.php",
                    "if [ -f config/app.php-- ]; then rm config/app.php--; fi"
                ]
            ];
        }

        $composerCommands = ['composer' => static::$composerCommands];

        $artisanInstallCommands = ['artisan install' => [$this->buildartisanInstallCommands()]];

        $npmCommands = ['npm' => static::$npmCommands];

        return array_filter(array_merge(
            $databaseCommands,
            $gitCommands,
            $formatEnvExampleCommand,
            $composerCommands,
            $artisanInstallCommands,
            $npmCommands
        ));
    }

    /**
     * The ProcessMaker artisan install command with the arguments
     * pre-populated (speeds everything up quite a bit)
     *
     * @return string
     */
    public function buildArtisanInstallCommands(): string
    {
        $redis_driver = extension_loaded('redis')
            ? 'phpredis'
            : 'predis';

        $install_command = [
            PHP_BINARY.' artisan processmaker:install',
            '--no-interaction',
            '--app-debug',
            '--telescope',
            '--db-password=',
            '--db-username=root',
            '--db-host=127.0.0.1',
            '--db-port=3306',
            '--data-driver=mysql',
            '--db-name=processmaker',
            '--url=http://processmaker.test',
            '--password=12345678',
            '--email=noreply@processmaker.test',
            '--username=admin',
            '--first-name=Change',
            '--last-name=Maker',
            "--redis-client=$redis_driver",
            '--redis-host=127.0.0.1'
        ];

        if ($this->branch !== '4.1-develop') {
            $install_command[] = '--session-domain=';
        }

        return implode(" ", $install_command).';';
    }

    /**
     * @param  string  $db_name
     * @param  string  $mysql_user
     *
     * @return string
     */
    public function buildDropAndCreateSqlCommand(string $db_name = 'processmaker', string $mysql_user = 'root'): string
    {
        return "mysql -u $mysql_user <<EOFMYSQL
DROP DATABASE $db_name;
EOFMYSQL

        mysql -u $mysql_user <<EOFMYSQL
CREATE DATABASE $db_name;
EOFMYSQL";
    }

    /**
     * @param  string  $path
     *
     * @return void
     */
    public function formatEnvFile(string $path = null): void
    {
        if (!$path) {
            $path = Config::codebasePath('.env');
        }

        if (!$this->files->exists($path)) {
            throw new DomainException(".env file could not be found: $path");
        }

        $find_and_remove = [
            'SESSION_DOMAIN=http://processmaker.test',
            'DOCKER_HOST_URL=http://processmaker.test',
            'APP_ENV=production',
        ];

        $append = [
            'APP_ENV=local',
            'API_SSL_VERIFY=0',
            'CACHE_DRIVER=redis',
            'DOCKER_HOST_URL=http://host.docker.internal',
            'PROCESSMAKER_SCRIPTS_TIMEOUT='.Cli::findExecutable('timeout'),
            'PROCESSMAKER_SCRIPTS_DOCKER='.Cli::findExecutable('docker'),
            'SESSION_DRIVER=redis',
            'SESSION_SECURE_COOKIE=false',
            'SESSION_DOMAIN=processmaker.test',
            'LARAVEL_ECHO_SERVER_PROTO=http',
            'LARAVEL_ECHO_SERVER_SSL_KEY=""',
            'LARAVEL_ECHO_SERVER_SSL_CERT=""',
            'NODE_BIN_PATH='.Cli::findExecutable('node'),
        ];

        $env_contents = $this->files->get($path);

        // Search for and remove the any of the strings
        // found in the $find_and_remove array
        foreach ($find_and_remove as $search_for) {
            $env_contents = Str::replace("$search_for\n", '', $env_contents);
        }

        // Append the remaining env variables to enable the
        // core codebase to function in a local environment
        $env_contents .= implode("\n", $append);

        // Save the file contents
        $this->files->putAsUser($path, $env_contents);
    }
}
