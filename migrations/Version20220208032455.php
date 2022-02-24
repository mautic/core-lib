<?php

declare(strict_types=1);

/*
 * @copyright   2022 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        https://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20220208032455 extends PreUpAssertionMigration
{
    protected function preUpAssertions(): void
    {
        $this->skipAssertion(function (Schema $schema) {
            $sql  = sprintf('select id from %s%s limit 1', $this->prefix, 'forms');
            $recordCount = $this->connection->executeQuery($sql)->fetchAll();

            return !(bool) $recordCount;
        }, 'Migration is not required.');
    }

    public function up(Schema $schema): void
    {
        $formFields = $this->getFormFields();

        foreach ($formFields as $formId => $formField) {
            $tableName  = $this->prefix.'form_results_'.$formId.'_'.$formField['alias'];

            // Get table schema
            $columns = $this->getTableSchema($schema, $tableName);
            if (empty($columns)) {
                continue;
            }

            $fields       = $formField['fields'];
            $deleteFields = array_diff($columns, $fields);

            $dropColumns   = array_map(function (string $column) {
                return sprintf('DROP COLUMN %s', $column);
            }, $deleteFields);

            if ($dropColumns) {
                $this->addSql(sprintf('ALTER TABLE %s %s', $tableName, implode(', ', $dropColumns)));
            }
        }
    }

    private function getFormFields(): array
    {
        $sqlString  = sprintf('SELECT f.id as form_id, f.alias as form_name, ff.id as field_id, ff.alias as column_name FROM %s%s f JOIN %s%s ff on ff.form_id = f.id', $this->prefix, 'forms', $this->prefix, 'form_fields');
        $results    = $this->connection->executeQuery($sqlString)->fetchAll();

        $formFields = [];
        foreach ($results as $row) {
            $formId = $row['form_id'];

            $formFields[$formId]['alias']                    = $row['form_name'];
            $formFields[$formId]['fields'][$row['field_id']] = $row['column_name'];
        }

        return $formFields;
    }

    private function getTableSchema(Schema $schema, string $tableName): array
    {
        try {
            $columns = $schema->getTable($tableName)->getColumns();
            unset($columns['submission_id'], $columns['form_id']);

            return array_keys($columns);
        } catch (\Exception $e) {
            return [];
        }
    }
}
