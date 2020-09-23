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
use Doctrine\Migrations\Exception\SkipMigration;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

final class Version20200916091643 extends AbstractMauticMigration
{
    public function preUp(Schema $schema): void
    {
        if ($schema->getTable($this->getTableName())->hasColumn('deduplicate')) {
            throw new SkipMigration("The deduplicate column has already been added to the {$this->getTableName()} table.");
        }
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE {$this->getTableName()} ADD deduplicate VARCHAR(32) DEFAULT NULL");
        $this->addSql("CREATE INDEX deduplicate_date_added ON {$this->getTableName()} (deduplicate, date_added)");
    }

    private function getTableName(): string
    {
        return $this->prefix.'notifications';
    }
}
