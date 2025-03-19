<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Tests\Functional\Doctrine\Helper;

use Doctrine\DBAL\Schema\Column;
use Mautic\CoreBundle\Doctrine\Helper\ColumnSchemaHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;

class ColumnSchemaHelperFunctionalTest extends MauticMysqlTestCase
{
    /**
     * @var LeadField
     */
    private $field;

    /**
     * @var ColumnSchemaHelper
     */
    private $schemaHelper;

    protected $useCleanupRollback = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->field        = $this->createCustomField();
        $this->schemaHelper = $this->getContainer()->get('mautic.schema.helper.column');
    }

    public function testUpdateColumnSchemaLengthSuccessfully(): void
    {
        $newLength = 100;
        $this->schemaHelper->updateColumnLength($this->field->getAlias(), $newLength);

        /** @var Column $column */
        $column = $this->schemaHelper->getColumns()[$this->field->getAlias()];

        $this->assertEquals($newLength, $column->getLength(), 'Column length updated.');
    }

    /**
     * @dataProvider dataUpdateColumnExceptionCheck
     */
    public function testUpdateColumnLengthThrowsException(string $column, int $len, string $message): void
    {
        $this->expectExceptionMessageMatches($message);
        $this->schemaHelper->updateColumnLength($column, $len);
    }

    /**
     * @return mixed[]
     */
    public function dataUpdateColumnExceptionCheck(): iterable
    {
        // name, length, exception msg.
        // Column name missing.
        yield ['', 10, '/The column name is should not be empty\/missing./'];

        // Column name does not exist.
        yield ['does_not_exists', 10, '/There is no column with name "does_not_exists" on table/'];

        // Out of range, when length is 0.
        yield ['custom_field_test', 0, '/Column length should be between 1 and 191./'];

        // Out of range, when length greater than 191.
        yield ['custom_field_test', 195, '/Column length should be between 1 and 191./'];
    }

    private function createCustomField(): LeadField
    {
        $field = new LeadField();
        $field->setType('text');
        $field->setObject('lead');
        $field->setGroup('core');
        $field->setLabel('Test field');
        $field->setAlias('custom_field_test');
        $field->setCharLengthLimit(64);

        /** @var FieldModel $fieldModel */
        $fieldModel = $this->getContainer()->get('mautic.lead.model.field');
        $fieldModel->saveEntity($field);
        $fieldModel->getRepository()->detachEntity($field);

        return $field;
    }
}
