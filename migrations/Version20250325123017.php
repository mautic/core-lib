<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20250325123017 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'form_fields';

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => $schema->getTable($this->getPrefixedTableName())->hasColumn('field_width'),
            'Column field_width already exists in the form_fields table'
        );
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName());
        $table->addColumn('field_width', Types::STRING, [
            'length'  => 50,
            'notnull' => true,
            'default' => '100%',
        ]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName());
        if ($table->hasColumn('field_width')) {
            $table->dropColumn('field_width');
        }
    }
}
