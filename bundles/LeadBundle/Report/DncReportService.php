<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Report;

use Mautic\LeadBundle\Helper\DncFormatterHelper;
use Mautic\LeadBundle\Model\DoNotContact;

final class DncReportService
{
    public function __construct(
        private DoNotContact $doNotContactModel,
        private DncFormatterHelper $dncFormatterHelper,
    ) {
    }

    /**
     * Returns the configuration for DNC columns used in reports.
     *
     * @return array<string, array<string, string>> an associative array defining DNC columns, including alias, label, type, and SQL formula
     */
    public function getDncColumns(): array
    {
        return [
            'dnc_list' => [
                'alias'   => 'dnc_list',
                'label'   => 'mautic.lead.report.dnc_list',
                'type'    => 'string',
                'formula' => '(SELECT GROUP_CONCAT(CONCAT(dnc.reason, \':\', dnc.channel) ORDER BY dnc.date_added DESC SEPARATOR \',\') FROM '.MAUTIC_TABLE_PREFIX.'lead_donotcontact dnc WHERE dnc.lead_id = l.id)',
            ],
        ];
    }

    /**
     * Returns the configuration for DNC filters used in reports.
     *
     * @return array<string, array<string, mixed>> an associative array defining DNC filters, including label, type, list options, and operators
     */
    public function getDncFilters(): array
    {
        $dncOptions = $this->doNotContactModel->getReasonChannelCombinations();

        $listOptions = [];
        foreach ($dncOptions as $dncOption) {
            $key               = "{$dncOption['channel']}:{$dncOption['reason']}";
            $label             = $this->dncFormatterHelper->printReasonWithChannel($dncOption['reason'], $dncOption['channel']);
            $listOptions[$key] = $label;
        }

        return [
            'dnc' => [
                'label'     => 'DNC',
                'type'      => 'multiselect',
                'list'      => $listOptions,
                'operators' => [
                    'in'       => 'mautic.core.operator.in',
                    'notIn'    => 'mautic.core.operator.notin',
                    'empty'    => 'mautic.core.operator.isempty',
                    'notEmpty' => 'mautic.core.operator.isnotempty',
                ],
            ],
        ];
    }

    /**
     * Processes and formats the DNC status display for each entry in the data array.
     *
     * @param array<int, array<string, mixed>> $data an array of data rows, each containing a 'dnc_list' key
     *
     * @return array<int, array<string, mixed>> the modified data array with formatted 'dnc_list' entries
     */
    public function processDncStatusDisplay(array $data): array
    {
        if (empty($data) || !array_key_exists('dnc_list', $data[0])) {
            return $data;
        }

        foreach ($data as &$row) {
            if (!empty($row['dnc_list'])) {
                $dncEntries = explode(',', $row['dnc_list']);
                $dncText    = array_map(function ($entry) {
                    list($reason, $channel) = explode(':', $entry);

                    return $this->dncFormatterHelper->printReasonWithChannel((int) $reason, $channel);
                }, $dncEntries);

                $row['dnc_list'] = implode(', ', $dncText);
            }
        }

        return $data;
    }
}
