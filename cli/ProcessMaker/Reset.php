<?php

namespace ProcessMaker\Cli;

use DomainException;
use \Config as ConfigFacade;
use \FileSystem as Fs;
use Illuminate\Support\Str;

class Reset
{
    protected static $gitCommands = [
        'git reset --hard',
        'git clean -d -f .',
        "git checkout {branch}",
        'git fetch --all',
        'git pull --force',
    ];

    protected static $composerCommands = [
        'composer install --optimize-autoloader --no-interaction --no-suggest --no-progress'
    ];

    protected static $npmCommands = [
        'npm install --non-interactive',
        'npm run dev --non-interactive'
    ];

    protected static $artisanInstallCommands = [
        // The ProcessMaker artisan install command with
        // the arguments pre-populated (speeds everything
        // up quite a bit)
        PHP_BINARY.' artisan processmaker:install \
            --no-interaction \
            --app-debug \
            --telescope \
            --db-password= \
            --db-username=root \
            --db-host=127.0.0.1 \
            --db-port=3306 \
            --data-driver=mysql \
            --db-name=processmaker \
            --url=http://processmaker.test \
            --password=12345678 \
            --email=noreply@processmaker.test \
            --username=admin \
            --first-name=Change \
            --last-name=Maker \
            --redis-client=phpredis \
            --redis-host=127.0.0.1 \
            --session-domain="";',
    ];

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
        $gitCommands = array_map(static function ($command) use ($branch) {
            return Str::replace('{branch}', $branch, $command);
        }, static::$gitCommands);

        $databaseCommands = $bounce_database
            ? ['database' => [$this->buildDropAndCreateSqlCommand()]]
            : [];

        $gitCommands /**************/ = ['git' => $gitCommands];
        $composerCommands /*********/ = ['composer' => static::$composerCommands];
        $artisanInstallCommands /***/ = ['artisan install' => static::$artisanInstallCommands];
        $npmCommands /**************/ = ['npm' => static::$npmCommands];

        return array_filter(array_merge(
            $databaseCommands,
            $gitCommands,
            $composerCommands,
            $artisanInstallCommands,
            $npmCommands
        ));
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
            $path = ConfigFacade::codebasePath().'/.env';
        }

        if (!Fs::exists($path)) {
            throw new DomainException(".env file could not be found: $path");
        }

        $find_and_remove = [
            'SESSION_DOMAIN=http://processmaker.test',
            'DOCKER_HOST_URL=http://processmaker.test',
            'APP_ENV=production',
        ];

        $append = [
            'APP_ENV=local',
            'SESSION_DRIVER=redis',
            'CACHE_DRIVER=redis',
            'PROCESSMAKER_SCRIPTS_TIMEOUT='.(new CommandLine())->runAsUser('which timeout'),
            'DOCKER_HOST_URL=http://host.docker.internal',
            'SESSION_SECURE_COOKIE=false',
            'SESSION_DOMAIN=processmaker.test',
            'LARAVEL_ECHO_SERVER_PROTO=http',
            'LARAVEL_ECHO_SERVER_SSL_KEY=""',
            'LARAVEL_ECHO_SERVER_SSL_CERT=""',
            'API_SSL_VERIFY=0',
            'NODE_BIN_PATH='.(new CommandLine())->runAsUser('which node'),
        ];

        $env_contents = Fs::get($path);

        // Search for and remove the any of the strings
        // found in the $find_and_remove array
        foreach ($find_and_remove as $search_for) {
            if (Str::contains($env_contents, "$search_for\n")) {
                $env_contents = Str::replace("$search_for\n", '', $env_contents);
            }
        }

        // Append the remaining env variables to enable the
        // core codebase to function in a local environment
        $env_contents .= implode("\n", $append);

        // Save the file contents
        Fs::putAsUser($path, $env_contents);
    }
}
