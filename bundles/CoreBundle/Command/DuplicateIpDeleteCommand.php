<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI Command to delete duplicate IP addresses.
 */
class DuplicateIpDeleteCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mautic:duplicateip:delete')
            ->setDescription('Deletes duplicate IP addresses that are not used in any other table')
            ->setHelp(
                <<<'EOT'
                The <info>%command.name%</info> command is used to delete duplicate IP addresses that are not used in any other table.

<info>php %command.full_name%</info>
EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getEntityManager();
        $ipAddressRepository = $em->getRepository('MauticCoreBundle:IpAddress');

        $deletedRows = $ipAddressRepository->deleteDuplicateIpAddresses();

        $output->writeln(sprintf('<info>%s duplicate IP adreeses has been deleted</info>', $deletedRows)); //TODO add translation?

        return 0;
    }
}
