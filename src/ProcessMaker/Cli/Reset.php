<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use DomainException;
use Illuminate\Support\Str;
use ProcessMaker\Cli\Facades\Install;
use ProcessMaker\Cli\Facades\CommandLine as Cli;
use ProcessMaker\Cli\Facades\Config;

use function extension_loaded;
use function implode;
use function array_map;
use function array_filter;
use function array_merge;

class Reset
{
    /**
     * @var \ProcessMaker\Cli\CommandLine
     */
    protected $cli;

    /**
     * @var \ProcessMaker\Cli\FileSystem
     */
    protected $files;

    /**
     * @var string
     */
    protected $branch = '';

    /**
     * @var array
     */
    protected static $artisanInstallCommands = [
        PHP_BINARY.' artisan passport:install --no-interaction',
        PHP_BINARY.' artisan storage:link --no-interaction'
    ];

    /**
     * @var array
     */
    protected static $gitCommands = [
        'git checkout {branch}',
    ];

    /**
     * @var array
     */
    protected static $composerCommands = [
        'composer install --optimize-autoloader --no-interaction --no-progress',
    ];

    /**
     * @var array
     */
    protected static $npmCommands = [
        'npm install --non-interactive --quiet',
        'npm run dev --non-interactive --quiet',
    ];

    /**
     * @param  \ProcessMaker\Cli\CommandLine  $cli
     * @param  \ProcessMaker\Cli\FileSystem  $files
     */
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
            }, static::$gitCommands),
        ];

        $databaseCommands = $bounce_database
            ? ['database' => [$this->buildDropAndCreateSqlCommand(
                Install::get('database_name'),
                Install::get('database_user'),
                Install::get('database_password')
            )]] : [];

        $dockerExecutable = Cli::findExecutable('docker');

        // todo Need to update the core repo to set the docker executable location as an arg for the artisan install command
        $formatEnvCommand = [];

        // This is a hot fix so you can properly set the config
        // to point to the correct docker executable
        if ($this->branch === '4.1-develop') {
            $formatEnvCommand = [
                '4.1 cleanup' => [
                    "sed -i -- 's+/usr/bin/docker+${dockerExecutable}+g' config/app.php",
                    'if [ -f config/app.php-- ]; then rm config/app.php--; fi',
                ],
            ];
        }

        // Find the composer executable
        $composer = $this->cli->findExecutable('composer');

        // Make sure the composer executable is referenced absolutely
        $composerCommands = ['composer' => array_map(static function ($line) use ($composer) {
            return str_replace('composer', $composer, $line);
        }, static::$composerCommands)];

        $artisanInstallCommands = ['artisan install' => array_merge(
            [$this->buildArtisanInstallCommand()],
            self::$artisanInstallCommands
        )];

        $npmCommands = ['npm' => static::$npmCommands];

        return array_filter(array_merge(
            $databaseCommands,
            $gitCommands,
            $formatEnvCommand,
            $composerCommands,
            $artisanInstallCommands,
            $npmCommands
        ));
    }

    /**
     * The ProcessMaker\Cli artisan install command with the arguments
     * pre-populated (speeds everything up quite a bit)
     */
    public function buildArtisanInstallCommand(): string
    {
        $redis_driver = extension_loaded('redis')
            ? 'phpredis'
            : 'predis';


        $install_command = [
            PHP_BINARY.' artisan processmaker:install',
            '--no-interaction',
            '--app-debug',
            '--telescope',
            '--db-password='.Install::get('database_password'),
            '--db-username='.Install::get('database_user'),
            '--db-host='.Install::get('database_host'),
            '--db-port='.Install::get('database_port'),
            '--data-driver=mysql',
            '--db-name='.Install::get('database_name'),
            '--url='.Install::get('url'),
            '--password='.Install::get('admin_password'),
            '--email='.Install::get('admin_email'),
            '--username='.Install::get('admin_username'),
            '--first-name='.Install::get('admin_first_name'),
            '--last-name='.Install::get('admin_last_name'),
            "--redis-client={$redis_driver}",
            '--redis-host='.Install::get('redis_host'),
        ];

        if ($this->branch !== '4.1-develop') {
            $install_command[] = '--session-domain=';
        }

        return implode(' ', $install_command).';';
    }

    /**
     * @param  string  $db_name
     * @param  string  $mysql_user
     * @param  string|null  $mysql_password
     *
     * @return string
     */
    public function buildDropAndCreateSqlCommand(
        string $db_name = 'processmaker',
        string $mysql_user = 'root',
        string $mysql_password = null): string
    {
        $command = "mysql -u ${mysql_user}";

        if ($mysql_password) {
            $command .= " -p ${mysql_password}";
        }

        return "$command <<EOFMYSQL
DROP DATABASE ${db_name};
EOFMYSQL

        $command <<EOFMYSQL
CREATE DATABASE ${db_name};
EOFMYSQL";
    }

    /**
     * @param  string|null  $path
     *
     * @return void
     */
    public function formatEnvFile(?string $path = null): void
    {
        if (!$path) {
            $path = Config::codebasePath('.env');
        }

        if (! $this->files->exists($path)) {
            throw new DomainException(".env file could not be found: {$path}");
        }

        $find_and_remove = [
            'SESSION_DOMAIN='.Install::read('url'),
            'DOCKER_HOST_URL='.Install::read('url'),
            'APP_ENV=production',
        ];

        // Reformat the url to a domain syntax e.g. http://processmaker.test/ -> processmaker.test
        $domain = Str::remove(['http://', 'https://'], Install::read('url'));

        $append = [
            'APP_ENV=local',
            'API_SSL_VERIFY=0',
            'CACHE_DRIVER=redis',
            'DOCKER_HOST_URL=http://host.docker.internal',
            'PROCESSMAKER_SCRIPTS_TIMEOUT='.Cli::findExecutable('timeout'),
            'PROCESSMAKER_SCRIPTS_DOCKER='.Cli::findExecutable('docker'),
            'SESSION_DRIVER=redis',
            'SESSION_SECURE_COOKIE=false',
            'SESSION_DOMAIN='.$domain,
            'LARAVEL_ECHO_SERVER_PROTO=http',
            'LARAVEL_ECHO_SERVER_SSL_KEY=""',
            'LARAVEL_ECHO_SERVER_SSL_CERT=""',
            'NODE_BIN_PATH='.Cli::findExecutable('node'),
        ];

        $env_contents = $this->files->get($path);

        // Search for and remove the any of the strings
        // found in the $find_and_remove array
        foreach ($find_and_remove as $search_for) {
            $env_contents = Str::replace("${search_for}\n", '', $env_contents);
        }

        // Append the remaining env variables to enable the
        // core codebase to function in a local environment
        $env_contents .= implode("\n", $append);

        // Save the file contents
        $this->files->putAsUser($path, $env_contents);
    }
}
