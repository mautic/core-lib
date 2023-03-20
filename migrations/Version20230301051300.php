<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;
use Mautic\SmsBundle\Entity\Sms;

final class Version20230301051300 extends PreUpAssertionMigration
{
    protected function preUpAssertions(): void
    {
        $this->skipAssertion(function (Schema $schema) {
            return $schema->getTable($this->getPrefixedTableName(SMS::TABLE_NAME))->hasColumn('media');
        }, 'Column media already exists');
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf("ALTER TABLE %s add media JSON NOT NULL DEFAULT ('{}');", $this->getPrefixedTableName(SMS::TABLE_NAME)));
    }
}
