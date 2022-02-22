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
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;
use Mautic\LeadBundle\Field\Helper\IndexHelper;

final class Version20220120123310 extends PreUpAssertionMigration
{
    private const TABLE = 'lead_lists';

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            function (Schema $schema) {
                $table = $schema->getTable($this->getPrefixedTableName(self::TABLE));

                return $table->hasIndex($this->getIndexName());
            },
            "Index {$this->getIndexName()} cannot be created because the index already exists"
        );
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE INDEX {$this->getIndexName()} ON {$this->getPrefixedTableName(self::TABLE)} (deleted)");
    }

    private function getIndexName(): string
    {
        return $this->prefix.'segment_deleted';
    }
}
