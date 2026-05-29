<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Command;

use Mautic\CoreBundle\Helper\ExitCode;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DeleteCompanyLeads extends Command
{
    public const COMMAND_NAME = 'mautic:company:delete_company_leads';

    private OutputInterface $output;

    public function __construct(
        private CompanyLeadRepository $companyLeadRepository,
        private CompanyRepository $companyRepository,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Delete Company referance from leads and update leads with new primary company.')
            ->addOption(
                '--company-id',
                '-i',
                InputOption::VALUE_REQUIRED,
                'Company id to delete references.',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $companyId    = (int) $input->getOption('company-id');
        $this->output = $output;

        // single entity
        if (!empty($companyId)) {
            $this->processDeleteCompany($companyId);

            return ExitCode::SUCCESS;
        }
        $deletedCompanies = $this->companyRepository->getDeletedCompanies();
        foreach ($deletedCompanies as $company) {
            $this->processDeleteCompany($company->getId());
        }

        return ExitCode::SUCCESS;
    }

    private function processDeleteCompany(int $companyId): void
    {
        $this->changePrimaryCompanyToLatest($companyId);
        $this->deleteCompanyLeads($companyId);
        $this->deleteCompanyPermanently($companyId);
    }

    private function changePrimaryCompanyToLatest(int $companyId): void
    {
        $this->output->writeln("<info>Updating with new primary company for company id {$companyId} which has been deleted.</info>");
        $this->companyLeadRepository->changePrimaryCompanyToLatest($companyId);
    }

    private function deleteCompanyLeads(int $companyId): void
    {
        $this->output->writeln("<info>Deleting all the company lead mapping for comany id {$companyId} which has been deleted.</info>");
        $this->companyLeadRepository->deleteCompanyLeads($companyId);
    }

    private function deleteCompanyPermanently(int $companyId): void
    {
        $this->output->writeln("<info>Deleting company for comany id {$companyId} permanently.</info>");
        $this->companyRepository->deleteCompanyPermanently($companyId);
    }
}
