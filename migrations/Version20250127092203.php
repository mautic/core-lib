<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20250127092203 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'user_invites';

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => $schema->hasTable($this->getPrefixedTableName(self::TABLE_NAME)),
            'Table '.self::TABLE_NAME.' already exists'
        );
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable($this->prefix.'user_invites');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
        $table->addColumn('email', 'string', ['length' => 191, 'notnull' => true]);
        $table->addColumn('token', 'string', ['length' => 64, 'notnull' => true]);
        $table->addColumn('expiration', 'datetime', ['notnull' => true]);
        $table->addColumn('used', 'boolean', ['notnull' => true, 'default' => false]);
        $table->addColumn('role_id', 'integer', ['unsigned' => true, 'notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['token'], 'UNIQ_USER_INVITES_TOKEN');
        $table->addIndex(['email'], 'IDX_USER_INVITES_EMAIL');
        $table->addIndex(['expiration'], 'IDX_USER_INVITES_EXPIRATION');
        $table->addIndex(['used'], 'IDX_USER_INVITES_USED');
        $table->addIndex(['role_id'], 'IDX_USER_INVITES_ROLE');
        $table->addForeignKeyConstraint(
            $this->getPrefixedTableName('roles'),
            ['role_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable($this->prefix.'user_invites');
    }
}
