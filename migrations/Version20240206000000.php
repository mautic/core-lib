<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20240206000000 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'forms';

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => $schema->getTable($this->getPrefixedTableName())->hasColumn('submission_limit'),
            "Table {$this->getPrefixedTableName()} already has 'submission_limit' column"
        );
        $this->skipAssertion(
            fn (Schema $schema) => $schema->getTable($this->getPrefixedTableName())->hasColumn('submission_limit_message'),
            "Table {$this->getPrefixedTableName()} already has 'submission_limit_message' column"
        );
        $this->skipAssertion(
            fn (Schema $schema) => $schema->getTable($this->getPrefixedTableName())->hasColumn('submission_count'),
            "Table {$this->getPrefixedTableName()} already has 'submission_count' column"
        );
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName());

        if (!$table->hasColumn('submission_limit')) {
            $table->addColumn('submission_limit', 'integer')->setNotnull(false);
        }

        if (!$table->hasColumn('submission_limit_message')) {
            $table->addColumn('submission_limit_message', 'text')->setNotnull(false);
        }

        if (!$table->hasColumn('submission_count')) {
            $table->addColumn('submission_count', 'integer')->setNotnull(true)->setDefault(0);
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
