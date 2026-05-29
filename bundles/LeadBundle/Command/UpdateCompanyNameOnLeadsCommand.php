<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Command;

use Mautic\CoreBundle\Helper\ExitCode;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateCompanyNameOnLeadsCommand extends Command
{
    public const COMMAND_NAME = 'mautic:company:update_lead_company';

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
            ->setDescription('Update company name in leads table.')
            ->addOption(
                '--company-id',
                '-i',
                InputOption::VALUE_REQUIRED,
                'Company id to update.',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $companyId    = (int) $input->getOption('company-id');
        $this->output = $output;

        // single entity
        if (!empty($companyId)) {
            $company = $this->companyRepository->getEntity($companyId);
            $this->processUpdateCompany($company);

            return ExitCode::SUCCESS;
        }
        $companies = $this->companyRepository->getEntities();
        foreach ($companies as $company) {
            $this->processUpdateCompany($company);
        }

        return ExitCode::SUCCESS;
    }

    private function processUpdateCompany(Company $company): void
    {
        $this->output->writeln("<info>Updating the updated company name on leads table for company id {$company->getId()}.</info>");
        $this->companyLeadRepository->updateCompanyNameOnLeads($company);
    }
}
