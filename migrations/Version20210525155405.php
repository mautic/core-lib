<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\SkipMigration;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

final class Version20210525155405 extends AbstractMauticMigration
{
    private string $uuidColumn = 'uuid';

    private string $table = 'reports';

    public function preUp(Schema $schema): void
    {
        if ($schema->getTable($this->prefix.$this->table)->hasColumn($this->uuidColumn)) {
            throw new SkipMigration('Schema includes this migration');
        }
    }

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
