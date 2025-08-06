<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;
use Mautic\PointBundle\Entity\Group;

final class Version20250805095503 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = Group::TABLE_NAME;

    public function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => $schema->getTable($this->getPrefixedTableName())->hasColumn('uuid'),
            'Column uuid already exists'
        );
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable($this->getPrefixedTableName());
        $table->addColumn('uuid', 'guid', ['nullable' => true]);
        $this->addSql("UPDATE `{$this->getPrefixedTableName()}` SET `uuid` = UUID() WHERE `uuid` IS NULL");
    }
}
