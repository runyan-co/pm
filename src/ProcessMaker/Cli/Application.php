<?php

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
    public function __construct($name = 'ProcessMaker Cli Tool', $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        if (!$this->getContainer()) {
            $this->useContainer(Container::getInstance());
        }

        // Register signal handlers for cleanup
        foreach ([SIGINT, SIGTERM] as $signal) {
            $this->getSignalRegistry()->register($signal, static function ($signal, $hasNext) {
                if (!$hasNext) {
                    Core::restoreIdeConfiguration();

                    exit(0);
                }
            });
        }
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultInputDefinition(): object
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
        }

        return parent::doRun($input, $output);
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
