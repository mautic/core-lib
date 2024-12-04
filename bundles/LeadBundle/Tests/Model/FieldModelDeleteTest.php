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
    // copied creation and deletion and getColumns() from app/bundles/LeadBundle/Tests/Model/FieldModelTest.php
    private ?object $fieldModel;
    private LeadField $leadField;
    private LeadField $leadField2;

    public function setUp(): void
    {
        $this->configParams['create_custom_field_in_background'] = $this->getName() === 'testBatchDeleteFields';

        parent::setUp();

        $this->connection->beginTransaction();

        $this->fieldModel = self::$container->get('mautic.lead.model.field');
    }
    
    public function testBatchCreateFields(): array
    {
        $leadField = new LeadField();
        $leadField->setName('Test Lead Field')
            ->setAlias('test_lead_field')
            ->setType('text')
            ->setObject('lead');

        $leadField2 = new LeadField();
        $leadField2->setName('Test Lead Field 2')
            ->setAlias('test_lead_field2')
            ->setType('text')
            ->setObject('lead');

        $companyField = new LeadField();
        $companyField->setName('Test Company Field')
            ->setAlias('test_company_field')
            ->setType('text')
            ->setObject('company');

        $companyField2 = new LeadField();
        $companyField2->setName('Test Company Field 2')
            ->setAlias('test_company_field2')
            ->setType('text')
            ->setObject('company');

        $this->fieldModel->saveEntities([$leadField, $leadField2, $companyField, $companyField2]);
        
        $this->assertCount(1, $this->getColumns('leads', $leadField->getAlias()));
        $this->assertCount(1, $this->getColumns('leads', $leadField2->getAlias()));
        $this->assertCount(1, $this->getColumns('companies', $companyField->getAlias()));
        $this->assertCount(1, $this->getColumns('companies', $companyField2->getAlias()));

        return [$leadField, $leadField2, $companyField, $companyField2];
    }

    /**
     * @depends testBatchCreateFields
     */
    public function testBatchDeleteFields(array $leadFields)
    {
        $this->assertCount(4, $leadFields);

        [$leadField, $leadField2, $companyField, $companyField2] = $leadFields;

//        $this->fieldModel->deleteEntities([$leadField->getId(), $leadField2->getId(), $companyField->getId(), $companyField2->getId()]);
//
//        $this->assertCount(0, $this->getColumns('leads', $leadField->getAlias()));
//        $this->assertCount(0, $this->getColumns('leads', $leadField2->getAlias()));
//        $this->assertCount(0, $this->getColumns('companies', $companyField->getAlias()));
//        $this->assertCount(0, $this->getColumns('companies', $companyField2->getAlias()));
//
        $jobRepository = $this->em->getRepository(Job::class);
        \assert($jobRepository instanceof JobRepository);

        $leadColumnDeleteJobs = $jobRepository->findAll();
        Assert::assertCount(4, $leadColumnDeleteJobs, 'There should be 4 jobs to delete lead column');
    }

    private function getColumns($table, $column)
    {
        $stmt = $this->connection->executeQuery(
            "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$this->connection->getDatabase()}' AND TABLE_NAME = '"
            .MAUTIC_TABLE_PREFIX
            ."$table' AND COLUMN_NAME = '$column'"
        );

        return $stmt->fetchAll();
    }

}