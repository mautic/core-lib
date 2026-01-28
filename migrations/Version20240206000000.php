<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;
use Mautic\FormBundle\Entity\Form;

final class Version20240206000000 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = Form::TABLE_NAME;

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => $schema->getTable($this->getPrefixedTableName())->hasColumn('submission_limit'),
            "Table {$this->getPrefixedTableName()} already has 'submission_limit' column"
        );
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName());
        if (!$table->hasColumn('submission_limit')) {
            $table->addColumn('submission_limit', 'integer', ['notnull' => false, 'default' => null]);
        }
        if (!$table->hasColumn('submission_limit_message')) {
            $table->addColumn('submission_limit_message', 'text', ['notnull' => false, 'default' => null]);
        }
        if (!$table->hasColumn('submission_count')) {
            $table->addColumn('submission_count', 'integer', ['notnull' => true, 'default' => 0]);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName());
        if ($table->hasColumn('submission_limit')) {
            $table->dropColumn('submission_limit');
        }
        if ($table->hasColumn('submission_limit_message')) {
            $table->dropColumn('submission_limit_message');
        }
        if ($table->hasColumn('submission_count')) {
            $table->dropColumn('submission_count');
        }
    }
}
