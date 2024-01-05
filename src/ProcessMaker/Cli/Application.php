<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Silly\Input\InputOption;
use Illuminate\Container\Container;
use ProcessMaker\Cli\Facades\Core;
use ProcessMaker\Cli\Facades\SnapshotsRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption as InputOptionAlias;

class Application extends \Silly\Application
{
    /**
     * @var bool
     */
    private static $verbose = false;

    /**
     * @param $name
     * @param $version
     */
    public function __construct($name = 'ProcessMaker Cli Tool', $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        if (!$this->getContainer()) {
            $this->useContainer(Container::getInstance());
        }
        
        $this->registerSignals();
    }

    /**
     * Register signal handlers for cleanup
     *
     * @return void
     */
    protected function registerSignals(): void
    {
        if (!\function_exists('pcntl_signal')) {
            return;
        }

        foreach ([SIGINT, SIGTERM] as $signal) {
            $this->getSignalRegistry()->register($signal, static function ($signal, $hasNext) {
                if (!$hasNext) {
                    Core::restoreIdeConfiguration();

                    exit($signal);
                }
            });
        }
    }

    /**
     * @inheritdoc
     */
    public function getDefaultInputDefinition(): object
    {
        return tap(parent::getDefaultInputDefinition(), function ($definition) {
            $definition->addOption($this->getTimingOption());
        });
    }

    /**
     * @inheritdoc
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasParameterOption(['--timing', '-t'], true)) {
            SnapshotsRepository::enable();
            SnapshotsRepository::setPid(getmypid());
        }

        if ($input->hasParameterOption(['--verbose', '-v', '-vv', '-vvv'], true)) {
            static::$verbose = true;
        }

        return parent::doRun($input, $output);
    }

    /**
     * @return bool
     */
    public function isVerbose(): bool
    {
        return static::$verbose === true;
    }

    /**
     * Generate an instance of the -t|--timing input option
     *
     * @return \Silly\Input\InputOption
     */
    protected function getTimingOption(): InputOption
    {
        $description = 'Record timing for all commands run and display them as a table when the command is finished';

        return new InputOption('timing', 't', InputOptionAlias::VALUE_NONE, $description);
    }
}
