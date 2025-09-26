<?php

namespace Mautic\LeadBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ListTypeaheadEvent extends Event
{
    private string $fieldAlias;

    private string $filter;

    /**
     * @var mixed[]
     */
    private array $dataArray = [];

    public function __construct(string $fieldAlias, string $filter)
    {
        $this->fieldAlias = $fieldAlias;
        $this->filter     = $filter;
    }

    public function getFieldAlias(): string
    {
        return $this->fieldAlias;
    }

    public function getFilter(): string
    {
        return $this->filter;
    }

    /**
     * @return mixed[]
     */
    public function getDataArray(): array
    {
        return $this->dataArray;
    }

    /**
     * @param mixed[] $dataArray
     */
    public function setDataArray(array $dataArray): void
    {
        $this->dataArray = $dataArray;
    }
}
