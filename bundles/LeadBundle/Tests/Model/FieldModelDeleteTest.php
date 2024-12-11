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
    public function setUp(): void
    {
        $this->configParams['create_custom_field_in_background'] = true;

        parent::setUp();
    }

    public function testBatchDeleteFields(): void
    {
        $this->connection->beginTransaction();

        $fieldModel = self::$container->get('mautic.lead.model.field');

        $leadField = new LeadField();
        $leadField->setName('Test Lead Field')
            ->setAlias('test_lead_field')
            ->setType('text')
            ->setObject('lead');

        $companyField = new LeadField();
        $companyField->setName('Test Company Field')
            ->setAlias('test_company_field')
            ->setType('text')
            ->setObject('company');

        try {
            $fieldModel->saveEntity($leadField);
        } catch (\Exception $e) {
        }

        try {
            $fieldModel->saveEntity($companyField);
        } catch (\Exception $e) {
        }

        $leadFieldRepository = $this->em->getRepository(LeadField::class);
        \assert($leadFieldRepository instanceof LeadFieldRepository);

        $this->assertCount(45, $leadFieldRepository->findAll(), 'There should be 43 + 2 fields');

        $jobRepository = $this->em->getRepository(Job::class);
        \assert($jobRepository instanceof JobRepository);

        $leadColumnCreateJobs = $jobRepository->findBy(['task' => 'createLeadColumn']);
        Assert::assertCount(2, $leadColumnCreateJobs, 'There should be 2 jobs to create lead column');

        $fieldModel->deleteEntities([$leadField->getId(), $companyField->getId()]);

        $leadColumnDeleteJobs = $jobRepository->findBy(['task' => 'deleteLeadColumn']);
        Assert::assertCount(2, $leadColumnDeleteJobs, 'There should be 2 jobs to delete lead column');
    }
}
