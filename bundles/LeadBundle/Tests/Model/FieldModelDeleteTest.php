<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Model\FieldModel;
use PHPUnit\Framework\Assert;

final class FieldModelDeleteTest extends MauticMysqlTestCase
{
    private int $leadFieldId;
    private int $companyFieldId;

    public function setUp(): void
    {
        parent::setUp();
        // $this->configParams['create_custom_field_in_background'] = true;
    }

    public function testBatchDeleteFields(): void
    {
        $this->connection->beginTransaction();

        $this->configParams['create_custom_field_in_background'] = false;
        $this->initColumnData();

        $leadFieldRepository = $this->em->getRepository(LeadField::class);
        \assert($leadFieldRepository instanceof LeadFieldRepository);

        Assert::assertCount(1, $leadFieldRepository->findBy(['alias' => 'test_lead_field']));
        Assert::assertCount(1, $leadFieldRepository->findBy(['alias' => 'test_company_field']));

        /** @var FieldModel $fieldModel */
        $fieldModel                                              = self::getContainer()->get('mautic.lead.model.field');
        $this->configParams['create_custom_field_in_background'] = true;

        $fieldModel->deleteEntities([$this->leadFieldId, $this->companyFieldId]);

        Assert::assertCount(0, $leadFieldRepository->findBy(['alias' => 'test_lead_field']));
        Assert::assertCount(0, $leadFieldRepository->findBy(['alias' => 'test_company_field']));
    }

    public function initColumnData(): void
    {
        /** @var FieldModel $fieldModel */
        $fieldModel = self::getContainer()->get('mautic.lead.model.field');

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

        $fieldModel->saveEntity($leadField);
        $fieldModel->saveEntity($companyField);
        $this->em->flush();

        $this->leadFieldId    = $leadField->getId();
        $this->companyFieldId = $companyField->getId();
    }
}
