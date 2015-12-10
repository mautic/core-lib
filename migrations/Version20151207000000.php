<?php
/**
 * @package     Mautic
 * @copyright   2015 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Migrations\SkipMigrationException;
use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

/**
 * Class Version20151207000000
 */

class Version20151207000000 extends AbstractMauticMigration
{
    /**
     * @param Schema $schema
     *
     * @throws SkipMigrationException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function preUp(Schema $schema)
    {
        if ($schema->hasTable($this->prefix.'email_copies')) {

            throw new SkipMigrationException('Schema includes this migration');
        }
    }

    /**
     * @param Schema $schema
     */
    public function mysqlUp(Schema $schema)
    {
        $this->addSql('CREATE TABLE '.$this->prefix.'email_copies (id VARCHAR(32) NOT NULL, date_created DATETIME NOT NULL, body LONGTEXT DEFAULT NULL, subject LONGTEXT DEFAULT NULL, PRIMARY KEY(id))  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE '.$this->prefix.'email_stats ADD copy_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$this->prefix.'email_stats ADD CONSTRAINT '.$this->generatePropertyName('email_stats', 'fk', array('copy_id')).' FOREIGN KEY (copy_id) REFERENCES '.$this->prefix.'email_copies (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX '.$this->generatePropertyName('email_stats', 'idx', array('copy_id')).' ON '.$this->prefix.'email_stats (copy_id)');

        $this->addSql('ALTER TABLE '.$this->prefix.'email_stats DROP FOREIGN KEY '.$this->findPropertyName('email_stats', 'fk', 'A832C1C9'));
        $this->addSql('ALTER TABLE '.$this->prefix.'email_stats ADD CONSTRAINT '.$this->generatePropertyName('email_stats', 'fk', array('email_id')).' FOREIGN KEY (email_id) REFERENCES '.$this->prefix.'emails (id) ON DELETE SET NULL');
    }

    /**
     * @param Schema $schema
     */
    public function postgresqlUp(Schema $schema)
    {
        $this->addSql('CREATE TABLE '.$this->prefix.'email_copies (id VARCHAR(32) NOT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, body TEXT DEFAULT NULL, subject TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE '.$this->prefix.'email_stats ADD copy_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE '.$this->prefix.'email_stats ADD CONSTRAINT '.$this->generatePropertyName('email_stats', 'fk', array('copy_id')).' FOREIGN KEY (copy_id) REFERENCES '.$this->prefix.'email_copies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX '.$this->generatePropertyName('email_stats', 'idx', array('copy_id')).' ON '.$this->prefix.'email_stats (copy_id)');

        $this->addSql('ALTER TABLE ' . $this->prefix . 'email_stats DROP CONSTRAINT ' . $this->findPropertyName('email_stats', 'fk', 'A832C1C9'));
        $this->addSql('ALTER TABLE '.$this->prefix.'email_stats ADD CONSTRAINT '.$this->generatePropertyName('email_stats', 'fk', array('email_id')).' FOREIGN KEY (email_id) REFERENCES '.$this->prefix.'emails (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
