<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20250127092204 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'user_invites';

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => $schema->getTable($this->getPrefixedTableName(self::TABLE_NAME))->hasColumn('role_id'),
            'Column role_id already exists in table '.self::TABLE_NAME
        );
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName(self::TABLE_NAME));

        $table->addColumn('role_id', 'integer', ['unsigned' => true, 'notnull' => false]);
        $table->addForeignKeyConstraint(
            $this->getPrefixedTableName('roles'),
            ['role_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addIndex(['role_id'], 'IDX_USER_INVITES_ROLE');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName(self::TABLE_NAME));

        $table->dropIndex('IDX_USER_INVITES_ROLE');
        $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY FK_USER_INVITES_ROLE', $this->getPrefixedTableName(self::TABLE_NAME)));
        $table->dropColumn('role_id');
    }
}
