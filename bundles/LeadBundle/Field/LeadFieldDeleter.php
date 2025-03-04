<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Field;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Exception\NoListenerException;
use Mautic\LeadBundle\Field\Dispatcher\FieldDeleteDispatcher;
use Mautic\LeadBundle\Field\Exception\AbortColumnUpdateException;

class LeadFieldDeleter
{
    public function __construct(private LeadFieldRepository $leadFieldRepository, private FieldDeleteDispatcher $fieldDeleteDispatcher)
    {
    }

    public function saveLeadFieldEntity(LeadField $leadField): void
    {
        $this->leadFieldRepository->saveEntity($leadField);
    }

    /**
     * @param bool $isBackground - if processing in background
     */
    public function deleteLeadFieldEntity(LeadField $leadField, bool $isBackground = false): void
    {
        try {
            $this->fieldDeleteDispatcher->dispatchPreDeleteEvent($leadField);
        } catch (NoListenerException) {
        } catch (AbortColumnUpdateException) { // if processing in background
            if (!$isBackground) {
                return;
            }
        }

        $leadField->deletedId = $leadField->getId();
        $this->leadFieldRepository->deleteEntity($leadField);

        try {
            $this->fieldDeleteDispatcher->dispatchPostDeleteEvent($leadField);
        } catch (NoListenerException) {
        }
    }

    public function deleteLeadFieldEntityWithoutColumnRemoved(LeadField $leadField): void
    {
        $leadField->setColumnIsNotRemoved();

        $this->saveLeadFieldEntity($leadField);
    }
}
