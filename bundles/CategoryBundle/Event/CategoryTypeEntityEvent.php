<?php

namespace Mautic\CategoryBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;

class CategoryTypeEntityEvent extends CommonEvent
{
    protected array $types = [];

    /**
     * Returns the array of Category Type Entity.
     */
    public function getCategoryTypeEntity(string $type): array
    {
        if ('global' === $type) {
            return $this->types;
        }

        return [$this->types[$type]];
    }

    /**
     * Adds the category type and class.
     */
    public function addCategoryTypeEntity(string $type, $class): void
    {
        if (!is_null($class)) {
            $this->types[$type] = $class;
        }
    }
}
