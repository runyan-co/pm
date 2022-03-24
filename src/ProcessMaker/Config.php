<?php

declare(strict_types=1);

namespace ProcessMaker;

use ProcessMaker\Facades\Install;

class Config
{
    /**
     * The default values, their descriptions, and keys for the config.json file
     *
     * @var array
     */
    public static $defaults = [

        'packages_path' => [
            'description' => 'Absolute path to the directory containing all local copies of enterprise composer packages',
            'default' => '',
        ],

        'codebase_path' => [
            'description' => 'Absolute path to the core processmaker/processmaker codebase directory',
            'default' => '',
        ],

        'slack_api_key' => [
            'description' => 'Enter your Slack API key',
            'default' => ''
        ],

        'google_places_api_key' => [
            'description' => 'Enter your Google Places API key',
            'default' => ''
        ],

        'database_name' => [
            'description' => 'Mysql database name to be used by the codebase',
            'default' => 'processmaker',
        ],

        'database_user' => [
            'description' => 'Mysql database username to be used by the codebase',
            'default' => 'root',
        ],

        'database_password' => [
            'description' => 'Mysql database password to be used by the codebase',
            'default' => '',
        ],

        'database_host' => [
            'description' => 'Mysql database host to be used by the codebase',
            'default' => '127.0.0.1',
        ],

        'database_port' => [
            'description' => 'Mysql database port to be used by the codebase',
            'default' => '3306',
        ],

        'url' => [
            'description' => 'Web-accessible url to the local running instance of the codebase',
            'default' => '',
        ],

        'admin_username' => [
            'description' => 'Admin username to be created during the artisan processmaker:install process',
            'default' => 'admin',
        ],

        'admin_password' => [
            'description' => 'Admin password to be created during the artisan processmaker:install process',
            'default' => '12345678',
        ],

        'admin_email' => [
            'description' => 'Admin email to be created during the artisan processmaker:install process',
            'default' => 'noreply@processmaker.test',
        ],

        'admin_first_name' => [
            'description' => 'Admin first name to be created during the artisan processmaker:install process',
            'default' => 'Change',
        ],

        'admin_last_name' => [
            'description' => 'Admin last name to be created during the artisan processmaker:install process',
            'default' => 'Maker',
        ],

        'redis_host' => [
            'description' => 'Redis host default',
            'default' => '127.0.0.1',
        ],
    ];

    /**
     * @return array|false|mixed|string
     */
    public function packagesPath()
    {
        $path = getenv('PACKAGES_PATH');

        if (!$path) {
            $path = Install::read('packages_path');
        }

        return $path;
    }

    /**
     * @param  string|null  $file_name
     *
     * @return array|false|mixed|string
     */
    public function codebasePath(?string $file_name = null)
    {
        $path = getenv('CODEBASE_PATH');

        if (!$path) {
            $path = Install::read('codebase_path');
        }

        return $file_name ? "${path}/${file_name}" : $path;
    }
}
