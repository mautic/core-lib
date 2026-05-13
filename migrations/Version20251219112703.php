<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20251219112703 extends PreUpAssertionMigration
{
    protected function preUpAssertions(): void
    {
        $this->skipAssertion(function (Schema $schema) {
            return $schema->getTable($this->getTableName())->hasIndex($this->getIndexName());
        }, sprintf('Index %s already exists', $this->getIndexName()));
    }

    public function up(Schema $schema): void
    {
        $query = sprintf(
            'ALTER TABLE %s ADD INDEX %s (is_scheduled, event_id, trigger_date)',
            $this->getTableName(),
            $this->getIndexName()
        );

        $this->addSql($query);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(sprintf(
            'ALTER TABLE %s DROP INDEX %s',
            $this->getTableName(),
            $this->getIndexName()
        ));
    }

    private function getTableName(): string
    {
        return $this->prefix.LeadEventLog::TABLE_NAME;
    }

    private function getIndexName(): string
    {
        return "{$this->prefix}idx_scheduled_events";
    }
}
