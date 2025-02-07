<?php

declare(strict_types=1);

namespace Mautic\CategoryBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;

class CategoryTypeEntityEvent extends CommonEvent
{
    /**
     * @var array<mixed>
     */
    protected array $types = [];

    /**
     * Returns the array of Category Type Entity.
     *
     * @return array<mixed>
     */
    public function getCategoryTypeEntity(string $type): array
    {
        if ('global' === $type) {
            return $this->types;
        }

        return [$this->types[$type]];
    }

    /**
     * @param null|array<mixed> $data
     */
    public function addCategoryTypeEntity(string $type, ?array $data): void
    {
        if (!empty($data)) {
            if (!isset($data['label'])) {
                $data['label'] = 'mautic.'.$type.'.'.$type;
            }
            $this->types[$type] = $data;
        }
    }
}
