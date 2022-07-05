<?php

declare(strict_types=1);

namespace ProcessMaker\Cli\Facades;

use Illuminate\Container\Container;

abstract class Facade
{
    /**
     * The key for the binding in the container.
     */
    public static function containerKey(): string
    {
        return 'ProcessMaker\Cli\\'.basename(str_replace('\\', '/', static::class));
    }

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
        return call_user_func_array([static::getInstance(), $method], $parameters);
    }

    /**
     * Get an/the instance of a given class from the container
     *
     * @param  mixed  ...$parameters
     *
     * @return object
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function getInstance(...$parameters): object
    {
        if (static::shouldBeSingleton()) {
            Container::getInstance()->singletonIf(static::containerKey());
        }

        return Container::getInstance()->make(static::containerKey(), $parameters);
    }

    /**
     * Instruct the container to resolve a class instance as a singleton
     *
     * @return bool
     */
    public static function shouldBeSingleton(): bool
    {
        $methodExists = method_exists($class = static::containerKey(), 'shouldBeSingleton');

        return $methodExists ? $class::shouldBeSingleton() : true;
    }
}

/**
 * @see \ProcessMaker\Cli\Application
 */
class Application extends Facade {}

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

/**
 * @see \ProcessMaker\Cli\SnapshotsRepository
 */
class SnapshotsRepository extends Facade {}

/**
 * @see \ProcessMaker\Cli\Docker
 */
class Docker extends Facade {}
