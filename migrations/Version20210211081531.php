<?php

declare(strict_types=1);

/*
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        https://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20210211081531 extends AbstractMauticMigration
{
    private $uuidColumn = 'uuid';

    private $tableList = [
        'assets',
        'campaign_events',
        'campaigns',
        'categories',
        'custom_field',
        'dynamic_content',
        'emails',
        'emails_beefree_metadata',
        'focus',
        'form_actions',
        'form_fields',
        'forms',
        'lead_fields',
        'lead_lists',
        'lead_tags',
        'message_channels',
        'messages',
        'pages',
        'point_trigger_events',
        'point_triggers',
        'points',
        'push_notifications',
        'stages',
        'sms_messages',
        'monitoring',
        'global_tokens',
        'beefree_rows',
    ];

    /**
     * @return bool
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     *
     * @description If a table already has `uuid` column, remove that TABLE from the list where we want to add column in the up()
     */
    public function preUp(Schema $schema): void
    {
        foreach ($this->tableList as $key => $table) {
            if ($schema->getTable($this->prefix.$table)->hasColumn($this->uuidColumn)) {
                unset($this->tableList[$key]);
            }
        }
    }

    /**
     * @description This method adds a `uuid` column to the list of remanining tables from `preup` and sets default value to NULL.
     * After processing the above, generates random values for uuid using MySql's built-in UUID() to populate the values replacing NULL's.
     */
    public function up(Schema $schema): void
    {
        if (0 === count($this->tableList)) {
            return;
        }

        $statements = $triggers = [];
        foreach ($this->tableList as $table) {
            // When we migrate to MySql 8.0.13+,
            // the below commented statement would suffice for settings default values using expression
            // ALTER TABLE `{$this->prefix}{$table}` ALTER `{$this->uuidColumn}` SET DEFAULT bin_to_uuid(UUID());

            $statements[] = "ALTER TABLE `{$this->prefix}{$table}` ADD COLUMN `{$this->uuidColumn}` char(36) default NULL;";
            $statements[] = "UPDATE `{$this->prefix}{$table}` SET `{$this->uuidColumn}` = UUID() WHERE `{$this->uuidColumn}` IS NULL;";
        }
        $batchSql = implode(' ', $statements);
        $this->addSql($batchSql);
    }
}
