<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Field;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Exception\NoListenerException;
use Mautic\LeadBundle\Field\Dispatcher\FieldDeleteDispatcher;
use Mautic\LeadBundle\Field\Exception\AbortColumnUpdateException;

class LeadFieldDeleter
{
    /**
     * @var LeadFieldRepository
     */
    private $leadFieldRepository;

    /**
     * @var FieldDeleteDispatcher
     */
    private $fieldDeleteDispatcher;

    public function __construct(LeadFieldRepository $leadFieldRepository, FieldDeleteDispatcher $fieldDeleteDispatcher)
    {
        $this->leadFieldRepository   = $leadFieldRepository;
        $this->fieldDeleteDispatcher = $fieldDeleteDispatcher;
    }

    public function saveLeadFieldEntity(LeadField $leadField)
    {
        $this->leadFieldRepository->saveEntity($leadField);
    }

    /**
     * @param bool $isBackground - if processing in background
     */
    public function deleteLeadFieldEntity(LeadField $leadField, bool $isBackground = false)
    {
        try {
            $this->fieldDeleteDispatcher->dispatchPreDeleteEvent($leadField);
        } catch (NoListenerException $e) {
        } catch (AbortColumnUpdateException $e) { //if processing in background
            if (!$isBackground) {
                return;
            }
        }

        $leadField->deletedId = $leadField->getId();
        $this->leadFieldRepository->deleteEntity($leadField);

        try {
            $this->fieldDeleteDispatcher->dispatchPostDeleteEvent($leadField);
        } catch (NoListenerException $e) {
        }
    }

    public function deleteLeadFieldEntityWithoutColumnRemoved(LeadField $leadField)
    {
        $leadField->setColumnIsNotRemoved();

        $this->saveLeadFieldEntity($leadField);
    }
}
