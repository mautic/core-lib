<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PluginBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FetchLeadsCommand.
 */
class FetchLeadsCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mautic:integration:fetchleads')
            ->setAliases(
                [
                    'mautic:integration:synccontacts',
                ]
            )
            ->setDescription('Fetch leads from integration.')
            ->addOption(
                '--integration',
                '-i',
                InputOption::VALUE_REQUIRED,
                'Fetch leads from integration. Integration must be enabled and authorised.',
                null
            )
            ->addOption('--start-date', '-d', InputOption::VALUE_REQUIRED, 'Set start date for updated values.')
            ->addOption(
                '--end-date',
                '-t',
                InputOption::VALUE_REQUIRED,
                'Set end date for updated values.'
            )
            ->addOption(
                '--fetch-all',
                null,
                InputOption::VALUE_NONE,
                'Get all CRM contacts whatever the date is. Should be used at instance initialization only'
            )
            ->addOption(
                '--time-interval',
                '-a',
                InputOption::VALUE_OPTIONAL,
                'Send time interval to check updates on Salesforce, it should be a correct php formatted time interval in the past eg:(-10 minutes)'
            )
            ->addOption(
                '--limit',
                '-l',
                InputOption::VALUE_OPTIONAL,
                'Number of records to process when syncing objects',
                25
            )
            ->addOption('--force', '-f', InputOption::VALUE_NONE, 'Force execution even if another process is assumed running.');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        /** @var \Mautic\CoreBundle\Factory\MauticFactory $factory */
        $factory = $container->get('mautic.factory');

        $translator  = $factory->getTranslator();
        $integration = $input->getOption('integration');
        $startDate   = $input->getOption('start-date');
        $endDate     = $input->getOption('end-date');
        $interval    = $input->getOption('time-interval');
        $limit       = $input->getOption('limit');

        if (!$interval) {
            $interval = '15 minutes';
        }
        if (!$startDate) {
            $startDate = date('c', strtotime('-'.$interval));
        }
        if (!$endDate) {
            $endDate = date('c');
        }

        if ($input->getOption('fetch-all')) {
            $interval  = '43199000 minutes';
            $startDate = date('c', strtotime('-'.$interval));
            $endDate   = date('c');
        }
        if ($integration && $startDate && $endDate) {
            /** @var \Mautic\PluginBundle\Helper\IntegrationHelper $integrationHelper */
            $integrationHelper = $factory->getHelper('integration');

            $integrationObject = $integrationHelper->getIntegrationObject($integration);
            $config            = $integrationObject->mergeConfigToFeatureSettings();
            $supportedFeatures = $integrationObject->getIntegrationSettings()->getSupportedFeatures();

            $params['start'] = $startDate;
            $params['end']   = $endDate;
            $params['limit'] = $limit;

            if (in_array('get_leads', $supportedFeatures)) {
                if ($integrationObject !== null && method_exists($integrationObject, 'getLeads') && (in_array('Lead', $config['objects']) || in_array('contacts', $config['objects'])
              || in_array('Leads', $config['objects'])
              || in_array('Contacts', $config['objects'])
                )) {
                    $output->writeln('<info>'.$translator->trans('mautic.plugin.command.fetch.leads', ['%integration%' => $integration]).'</info>');
                    if (strtotime($startDate) > strtotime('-30 days') || $input->getOption('fetch-all')) {
                        $processed = intval($integrationObject->getLeads($params));

                        $output->writeln('<comment>'.$translator->trans('mautic.plugin.command.fetch.leads.starting').'</comment>');

                        $output->writeln('<comment>'.$translator->trans('mautic.plugin.command.fetch.leads.events_executed', ['%events%' => $processed]).'</comment>'."\n");
                    } else {
                        $output->writeln('<error>'.$translator->trans('mautic.plugin.command.fetch.leads.wrong.date').'</error>');
                    }
                }
            }

            if ($integrationObject !== null && method_exists($integrationObject, 'getCompanies') && in_array('company', $config['objects'])) {
                $output->writeln('<info>'.$translator->trans('mautic.plugin.command.fetch.companies', ['%integration%' => $integration]).'</info>');

                if (strtotime($startDate) > strtotime('-30 days') || $input->getOption('fetch-all')) {
                    $processed = intval($integrationObject->getCompanies($params));

                    $output->writeln('<comment>'.$translator->trans('mautic.plugin.command.fetch.companies.starting').'</comment>');

                    $output->writeln('<comment>'.$translator->trans('mautic.plugin.command.fetch.companies.events_executed', ['%events%' => $processed]).'</comment>'."\n");
                } else {
                    $output->writeln('<error>'.$translator->trans('mautic.plugin.command.fetch.leads.wrong.date').'</error>');
                }
            }

            if (in_array('push_leads', $supportedFeatures)) {
                $output->writeln('<info>'.$translator->trans('mautic.plugin.command.pushing.leads', ['%integration%' => $integration]).'</info>');
                list($updated, $created) = $integrationObject->pushLeads($params);
                $output->writeln(
                    '<comment>'.$translator->trans(
                        'mautic.plugin.command.fetch.pushing.leads.events_executed',
                        [
                            '%updated%' => $updated,
                            '%created%' => $created,
                        ]
                    )
                    .'</comment>'."\n"
                );
            }
        }

        return true;
    }
}
