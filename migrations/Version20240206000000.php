<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

final class Version20240206000000 extends AbstractMauticMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE {$this->prefix}forms ADD submission_limit INT DEFAULT NULL, ADD submission_limit_message LONGTEXT DEFAULT NULL, ADD submission_count INT NOT NULL DEFAULT 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE {$this->prefix}forms DROP submission_limit, DROP submission_limit_message, DROP submission_count");
    }
}
