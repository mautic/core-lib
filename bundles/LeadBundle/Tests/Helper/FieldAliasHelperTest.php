<?php

namespace Mautic\LeadBundle\Tests\Helper;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Helper\FieldAliasHelper;
use Mautic\LeadBundle\Model\FieldModel;

class FieldAliasHelperTest extends \PHPUnit\Framework\TestCase
{
    private FieldAliasHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $fieldRepository = $this->createMock(LeadFieldRepository::class);
        $fieldModel      = $this->getMockBuilder(FieldModel::class)
            ->onlyMethods(['cleanAlias', 'getRepository'])
            ->disableOriginalConstructor()
            ->getMock();

        $fieldRepository->method('getAliases')->willReturn([
            'title',
            'firstname',
            'lastname',
        ]);

<<<<<<< HEAD
        $this->fieldModel->method('cleanAlias')->willReturnCallback(fn (): mixed => func_get_args()[0]);
=======
        $fieldModel->method('cleanAlias')->willReturnCallback(fn () => func_get_args()[0]);
>>>>>>> f71dbbecbb ([tests] inline setUp properties used just once)

        $fieldModel->method('getRepository')->willReturn($fieldRepository);

        $this->helper = new FieldAliasHelper($fieldModel);
    }

    public function testDuplicatedAliasWithAliasSet(): void
    {
        $field = new LeadField();
        $field->setAlias('title');
        $field = $this->helper->makeAliasUnique($field);

        $this->assertEquals('title1', $field->getAlias());
    }

    public function testDuplicatedAliasWithAliasEmpty(): void
    {
        $field = new LeadField();
        $field->setName('title');
        $field = $this->helper->makeAliasUnique($field);

        $this->assertEquals('title1', $field->getAlias());
    }

    public function testUniqueAliasWithAliasEmpty(): void
    {
        $field = new LeadField();
        $field->setName('phone');
        $field = $this->helper->makeAliasUnique($field);

        $this->assertEquals('phone', $field->getAlias());
    }
}
