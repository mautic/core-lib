<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Command;

use Mautic\CoreBundle\Templating\Helper\FormatterHelper;
use Mautic\LeadBundle\Event\ContactExportSchedulerEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\ContactExportSchedulerModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ContactScheduledExportCommand extends Command
{
    public const COMMAND_NAME = 'mautic:contacts:scheduled_export';

    private ContactExportSchedulerModel $contactExportSchedulerModel;
    private EventDispatcherInterface $eventDispatcher;
    private FormatterHelper $formatterHelper;

    public function __construct(
        ContactExportSchedulerModel $contactExportSchedulerModel,
        EventDispatcherInterface $eventDispatcher,
        FormatterHelper $formatterHelper
    ) {
        $this->contactExportSchedulerModel = $contactExportSchedulerModel;
        $this->eventDispatcher             = $eventDispatcher;
        $this->formatterHelper             = $formatterHelper;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Export contacts which are scheduled in `contact_export_scheduler` table.')
            ->addOption(
                '--ids',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated contact_export_scheduler ids.'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids                     = $this->formatterHelper->simpleCsvToArray($input->getOption('ids'), 'int');
        $contactExportSchedulers = $this->contactExportSchedulerModel->getRepository()->findBy(['id' => $ids]);
        $count                   = 0;

        foreach ($contactExportSchedulers as $contactExportScheduler) {
            $contactExportSchedulerEvent = new ContactExportSchedulerEvent($contactExportScheduler);
            $this->eventDispatcher->dispatch(LeadEvents::CONTACT_EXPORT_PREPARE_FILE, $contactExportSchedulerEvent);
            $this->eventDispatcher->dispatch(LeadEvents::CONTACT_EXPORT_SEND_EMAIL, $contactExportSchedulerEvent);
            $this->eventDispatcher->dispatch(LeadEvents::POST_CONTACT_EXPORT_SEND_EMAIL, $contactExportSchedulerEvent);
            ++$count;
        }

        $output->writeln('Contact export email(s) sent: '.$count);

        return 0;
    }
}
