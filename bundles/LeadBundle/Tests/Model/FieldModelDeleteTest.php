<?php

namespace Mautic\LeadBundle\Tests\Model;

use Mautic\AllydeBundle\Entity\Job;
use Mautic\AllydeBundle\Entity\JobRepository;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
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
    }
    
    public function testBatchCreateFields()
    {
        $this->fieldModel = self::$container->get('mautic.lead.model.field');

        $this->leadField = new LeadField();
        $this->leadField->setName('Test Lead Field')
            ->setAlias('test_lead_field')
            ->setType('text')
            ->setObject('lead');

        $this->leadField2 = new LeadField();
        $this->leadField2->setName('Test Lead Field 2')
            ->setAlias('test_lead_field2')
            ->setType('text')
            ->setObject('lead');

        $this->companyField = new LeadField();
        $this->companyField->setName('Test Company Field')
            ->setAlias('test_company_field')
            ->setType('text')
            ->setObject('company');

        $this->companyField2 = new LeadField();
        $this->companyField2->setName('Test Company Field 2')
            ->setAlias('test_company_field2')
            ->setType('text')
            ->setObject('company');

        $this->fieldModel->saveEntities([$this->leadField, $this->leadField2, $this->companyField, $this->companyField2]);
        
        $this->assertCount(1, $this->getColumns('leads', $this->leadField->getAlias()));
        $this->assertCount(1, $this->getColumns('leads', $this->leadField2->getAlias()));
        $this->assertCount(1, $this->getColumns('companies', $this->companyField->getAlias()));
        $this->assertCount(1, $this->getColumns('companies', $this->companyField2->getAlias()));
        
    }

    /**
     * @depends testBatchCreateFields
     */
    public function testBatchDeleteFields()
    {

        $this->fieldModel->deleteEntities([$this->leadField->getId(), $this->leadField2->getId(), $this->companyField->getId(), $this->companyField2->getId()]);

        $this->assertCount(0, $this->getColumns('leads', $this->leadField->getAlias()));
        $this->assertCount(0, $this->getColumns('leads', $this->leadField2->getAlias()));
        $this->assertCount(0, $this->getColumns('companies', $this->companyField->getAlias()));
        $this->assertCount(0, $this->getColumns('companies', $this->companyField2->getAlias()));

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