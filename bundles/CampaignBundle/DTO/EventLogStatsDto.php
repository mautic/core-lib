<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\DTO;

final readonly class EventLogStatsDto
{
    public function __construct(
        private int $totalExecutions,
        private int $pendingExecutions,
        private int $negativePathCount,
        private int $positivePathCount,
        private ?\DateTime $firstExecutionDate,
        private ?\DateTime $lastExecutionDate,
    ) {
    }

    public function getTotalExecutions(): int
    {
        return $this->totalExecutions;
    }

    public function getPendingExecutions(): int
    {
        return $this->pendingExecutions;
    }

    public function getNegativePathCount(): int
    {
        return $this->negativePathCount;
    }

    public function getPositivePathCount(): int
    {
        return $this->positivePathCount;
    }

    public function getFirstExecutionDate(): ?\DateTime
    {
        return $this->firstExecutionDate;
    }

    public function getLastExecutionDate(): ?\DateTime
    {
        return $this->lastExecutionDate;
    }
}
