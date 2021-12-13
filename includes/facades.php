<?php

use Illuminate\Container\Container;

class ClassFacade
{
    /**
     * The key for the binding in the container.
     *
     * @return string
     */
    public static function containerKey(): string
    {
        return 'ProcessMaker\\Cli\\'.basename(str_replace('\\', '/', static::class));
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
        $resolvedInstance = Container::getInstance()->make(static::containerKey());

        return call_user_func_array([$resolvedInstance, $method], $parameters);
    }
}

/**
 * @see \ProcessMaker\Cli\CommandLine
 */
class CommandLine extends ClassFacade {}

/**
 * @see \ProcessMaker\Cli\FileSystem
 */
class FileSystem extends ClassFacade {}

/**
 * @see \ProcessMaker\Cli\Packages
 */
class Packages extends ClassFacade {}

/**
 * @see \ProcessMaker\Cli\Install
 */
class Install extends ClassFacade {}

/**
 * @see \ProcessMaker\Cli\ProcessManager
 */
class ProcessManager extends ClassFacade {}

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
