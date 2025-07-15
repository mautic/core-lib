<?php

namespace Mautic\CoreBundle\Tests\Unit\Command\src;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockInterface;

class FakeModeratedCommand extends ModeratedCommand
{
    protected function configure()
    {
        $this->setName('mautic:fake:command');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkRunStatus($input, $output);

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

    public function forceCompleteRun(): void
    {
        $this->completeRun();
    }

    public function setLock(?LockInterface $lock = null): void
    {
        $reflection = new \ReflectionClass($this);
        $property   = $reflection->getParentClass()->getProperty('lock');
        $property->setValue($this, $lock);
    }
}
