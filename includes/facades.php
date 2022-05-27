<?php

declare(strict_types=1);

namespace ProcessMaker\Cli\Facades;

use Illuminate\Container\Container;

abstract class Facade
{
    /**
     * Call a non-static method on the facade.
     *
     * @param  string  $method
     * @param  array  $parameters
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        Container::getInstance()->singletonIf(static::containerKey());

        return call_user_func_array([static::getInstance(), $method], $parameters);
    }

    /**
     * The key for the binding in the container.
     */
    public static function containerKey(): string
    {
        return 'ProcessMaker\Cli\\'.basename(str_replace('\\', '/', static::class));
    }

    /**
     * @return mixed|object
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function getInstance()
    {
        return Container::getInstance()->make(static::containerKey());
    }
}

/**
 * @see \ProcessMaker\Cli\CommandLine
 */
class CommandLine extends Facade {}

/**
 * @see \ProcessMaker\Cli\FileSystem
 */
class FileSystem extends Facade {}

/**
 * @see \ProcessMaker\Cli\Packages
 */
class Packages extends Facade {}

/**
 * @see \ProcessMaker\Cli\Install
 */
class Install extends Facade {}

/**
 * @see \ProcessMaker\Cli\ParallelRun
 */
class ParallelRun extends Facade {}

/**
 * @see \ProcessMaker\Cli\Composer
 */
class Composer extends Facade {}

/**
 * @see \ProcessMaker\Cli\Git
 */
class Git extends Facade {}

/**
 * @see \ProcessMaker\Cli\PackagesCi
 */
class PackagesCi extends Facade {}

/**
 * @see \ProcessMaker\Cli\Config
 */
class Config extends Facade {}

/**
 * @see \ProcessMaker\Cli\Supervisor
 */
class Supervisor extends Facade {}

/**
 * @see \ProcessMaker\Cli\Reset
 */
class Reset extends Facade {}

/**
 * @see \ProcessMaker\Cli\IDE
 */
class IDE extends Facade {}

/**
 * @see \ProcessMaker\Cli\Environment
 */
class Environment extends Facade {}

/**
 * @see \ProcessMaker\Cli\Logs
 */
class Logs extends Facade {}

/**
 * @see \ProcessMaker\Cli\Core
 */
class Core extends Facade {}
