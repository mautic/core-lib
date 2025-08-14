<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20230522141144 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'plugin_citrix_events';

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => !$schema->hasTable($this->getPrefixedTableName()),
            "Table {$this->getPrefixedTableName()} does not exist"
        );
    }

    public function up(Schema $schema): void
    {
        $schema->dropTable($this->getPrefixedTableName());
    }
}
