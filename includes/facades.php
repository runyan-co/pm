<?php

declare(strict_types=1);

namespace ProcessMaker\Facades;

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
        Container::getInstance()->singletonIf($key = static::containerKey());

        $resolvedInstance = Container::getInstance()->make($key);

        return call_user_func_array([$resolvedInstance, $method], $parameters);
    }

    /**
     * The key for the binding in the container.
     */
    public static function containerKey(): string
    {
        return 'ProcessMaker\\'.basename(str_replace('\\', '/', static::class));
    }
}

/**
 * @see \ProcessMaker\CommandLine
 */
class CommandLine extends Facade {}

/**
 * @see \ProcessMaker\FileSystem
 */
class FileSystem extends Facade {}

/**
 * @see \ProcessMaker\Packages
 */
class Packages extends Facade {}

/**
 * @see \ProcessMaker\Install
 */
class Install extends Facade {}

/**
 * @see \ProcessMaker\ProcessManager
 */
class ProcessManager extends Facade {}

/**
 * @see \ProcessMaker\Composer
 */
class Composer extends Facade {}

/**
 * @see \ProcessMaker\Git
 */
class Git extends Facade {}

/**
 * @see \ProcessMaker\PackagesCi
 */
class PackagesCi extends Facade {}

/**
 * @see \ProcessMaker\Config
 */
class Config extends Facade {}

/**
 * @see \ProcessMaker\Supervisor
 */
class Supervisor extends Facade {}

/**
 * @see \ProcessMaker\Reset
 */
class Reset extends Facade {}

/**
 * @see \ProcessMaker\Reset
 */
class IDE extends Facade {}

/**
 * @see \ProcessMaker\Reset
 */
class Environment extends Facade {}
