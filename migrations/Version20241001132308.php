<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20241001132308 extends PreUpAssertionMigration
{
    protected static $tableName = 'webhook_queue_failed';

    public function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => !$schema->hasTable($this->getPrefixedTableName()),
            "Table {$this->getPrefixedTableName()} does not exists."
        );
    }

    public function up(Schema $schema): void
    {
        $schema->dropTable($this->getPrefixedTableName());
    }
}
