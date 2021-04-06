<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20210330075323 extends PreUpAssertionMigration
{
    protected function preUpAssertions(): void
    {
        $tableName = $this->getPrefixedTableName('pages');
        $this->skipAssertion(
            function (Schema $schema) use ($tableName) {
                return $schema->getTable($tableName)->hasColumn('public_preview');
            },
            sprintf('Column %s already exists in table %s', 'public_preview', $tableName)
        );
    }

    public function up(Schema $schema): void
    {
        $tableName = $this->getPrefixedTableName('pages');
        $this->addSql("ALTER TABLE {$tableName} ADD public_preview TINYINT(1) DEFAULT 1 NOT NULL;");
    }

    public function down(Schema $schema): void
    {
        $tableName = $this->getPrefixedTableName('pages');
        $this->addSql("ALTER TABLE {$tableName} DROP public_preview;");
    }
}
