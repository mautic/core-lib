<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20230601082815 extends PreUpAssertionMigration
{
    protected function preUpAssertions(): void
    {
        $this->skipAssertion(function (Schema $schema) {
            $sql         = sprintf('select category_id from %s%s where category_id is null', $this->prefix, 'beefree_rows');
            $recordCount = $this->connection->executeQuery($sql)->rowCount();

            return !$recordCount;
        }, 'Migration is not required.');
    }

    public function up(Schema $schema): void
    {
        $data = [
            'is_published'  => 1,
            'date_added'    => date('Y-m-d H:i:s'),
            'date_modified' => date('Y-m-d H:i:s'),
            'title'         => 'BeeFreeRowDefault',
            'alias'         => 'beefreerowdefault',
            'bundle'        => 'beefree:rows',
        ];

        $this->connection->insert($this->prefix.'categories', $data);
        $id = $this->connection->lastInsertId();
        $this->addSql("UPDATE `{$this->prefix}beefree_rows` SET `category_id` = {$id} WHERE `category_id` IS NULL;");
    }
}
