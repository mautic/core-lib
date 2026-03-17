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
    protected $useCleanupRollback = false;

    public function testBatchDeleteFields(): void
    {
        $this->configParams['create_custom_field_in_background'] = false;
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

        $leadFieldRepository = $this->em->getRepository(LeadField::class);
        \assert($leadFieldRepository instanceof LeadFieldRepository);

        Assert::assertCount(1, $leadFieldRepository->findBy(['alias' => 'test_lead_field']));
        Assert::assertCount(1, $leadFieldRepository->findBy(['alias' => 'test_company_field']));

        $this->configParams['create_custom_field_in_background'] = true;

        $fieldModel->deleteEntities([$leadField->getId(), $companyField->getId()]);

        Assert::assertCount(0, $leadFieldRepository->findBy(['alias' => 'test_lead_field']));
        Assert::assertCount(0, $leadFieldRepository->findBy(['alias' => 'test_company_field']));
    }
}
