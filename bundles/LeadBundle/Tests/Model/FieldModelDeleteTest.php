<?php

namespace Mautic\LeadBundle\Tests\Model;

use Mautic\AllydeBundle\Entity\Job;
use Mautic\AllydeBundle\Entity\JobRepository;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use PHPUnit\Framework\Assert;

class FieldModelDeleteTest extends MauticMysqlTestCase
{
    private ?object $fieldModel;
    private LeadField $leadField;
    private LeadField $companyField;

    public function setUp(): void
    {
        $this->configParams['create_custom_field_in_background'] = 'testBatchDeleteFields' === $this->getName();

        parent::setUp();

        $this->connection->beginTransaction();

        $this->fieldModel = self::$container->get('mautic.lead.model.field');

        $this->leadField = new LeadField();
        $this->leadField->setName('Test Lead Field')
            ->setAlias('test_lead_field')
            ->setType('text')
            ->setObject('lead');

        $this->companyField = new LeadField();
        $this->companyField->setName('Test Company Field')
            ->setAlias('test_company_field')
            ->setType('text')
            ->setObject('company');

        try {
            $this->fieldModel->saveEntity($this->leadField);
        } catch (\Exception $e) {
        }

        try {
            $this->fieldModel->saveEntity($this->companyField);
        } catch (\Exception $e) {
        }
    }

    public function testBatchDeleteFields(): void
    {
        $leadFieldRepository = $this->em->getRepository(LeadField::class);
        \assert($leadFieldRepository instanceof LeadFieldRepository);

        $this->assertCount(45, $leadFieldRepository->findAll(), 'There should be 43 + 2 fields');

        $jobRepository = $this->em->getRepository(Job::class);
        \assert($jobRepository instanceof JobRepository);

        $leadColumnCreateJobs = $jobRepository->findBy(['task' => 'createLeadColumn']);
        Assert::assertCount(2, $leadColumnCreateJobs, 'There should be 2 jobs to create lead column');

        $this->fieldModel->deleteEntities([$this->leadField->getId(), $this->companyField->getId()]);

        $leadColumnDeleteJobs = $jobRepository->findBy(['task' => 'deleteLeadColumn']);
        Assert::assertCount(2, $leadColumnDeleteJobs, 'There should be 2 jobs to delete lead column');
    }
}
