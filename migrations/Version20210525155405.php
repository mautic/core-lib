<?php

declare(strict_types=1);

/*
 * @copyright   2021 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        https://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\SkipMigration;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20210525155405 extends AbstractMauticMigration
{
    private $uuidColumn = 'uuid';

    private $table = 'reports';

    /**
     * @return bool
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     *
     * @description If a table already has `uuid` column, remove that TABLE from the list where we want to add column in the up()
     */
    public function preUp(Schema $schema): void
    {
        if ($schema->getTable($this->prefix.$this->table)->hasColumn($this->uuidColumn)) {
            throw new SkipMigration('Schema includes this migration');
        }
    }

    /**
     * @description This method adds a `uuid` column to the list of remanining tables from `preup` and sets default value to NULL.
     * After processing the above, generates random values for uuid using MySql's built-in UUID() to populate the values replacing NULL's.
     */
    public function up(Schema $schema): void
    {
        $statements = [];

        // When we migrate to MySql 8.0.13+,
        // the below commented statement would suffice for settings default values using expression
        // ALTER TABLE `{$this->prefix}{$table}` ALTER `{$this->uuidColumn}` SET DEFAULT bin_to_uuid(UUID());

        $statements[] = "ALTER TABLE `{$this->prefix}{$this->table}` ADD COLUMN `{$this->uuidColumn}` char(36) default NULL;";
        $statements[] = "UPDATE `{$this->prefix}{$this->table}` SET `{$this->uuidColumn}` = UUID() WHERE `{$this->uuidColumn}` IS NULL;";
        $batchSql     = implode(' ', $statements);
        $this->addSql($batchSql);
    }
}
