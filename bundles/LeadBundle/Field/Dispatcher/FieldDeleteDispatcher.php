<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Field\Dispatcher;

use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Event\LeadFieldEvent;
use Mautic\LeadBundle\Exception\NoListenerException;
use Mautic\LeadBundle\Field\Exception\AbortColumnUpdateException;
use Mautic\LeadBundle\Field\Settings\BackgroundSettings;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FieldDeleteDispatcher
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var BackgroundSettings
     */
    private $backgroundSettings;

    public function __construct(EventDispatcherInterface $dispatcher, EntityManager $entityManager, BackgroundSettings $backgroundSettings)
    {
        $this->dispatcher         = $dispatcher;
        $this->entityManager      = $entityManager;
        $this->backgroundSettings = $backgroundSettings;
    }

    /**
     * @throws NoListenerException
     * @throws AbortColumnUpdateException
     */
    public function dispatchPreDeleteEvent(LeadField $entity): LeadFieldEvent
    {
        $shouldProcessInBackground = $this->backgroundSettings->shouldProcessColumnChangeInBackground();
        if ($shouldProcessInBackground) {
            throw new AbortColumnUpdateException('Column change will be processed in background job');
        }

        return $this->dispatchEvent(LeadEvents::FIELD_PRE_DELETE, $entity);
    }

    /**
     * @throws NoListenerException
     */
    public function dispatchPostDeleteEvent(LeadField $entity): LeadFieldEvent
    {
        return $this->dispatchEvent(LeadEvents::FIELD_POST_DELETE, $entity);
    }

    /**
     * @param string $action - Use constant from LeadEvents class (e.g. LeadEvents::FIELD_PRE_SAVE)
     *
     * @throws NoListenerException
     */
    private function dispatchEvent($action, LeadField $entity, LeadFieldEvent $event = null): LeadFieldEvent
    {
        if (!$this->dispatcher->hasListeners($action)) {
            throw new NoListenerException('There is no Listener for this event');
        }

        if (null === $event) {
            $event = new LeadFieldEvent($entity);
            $event->setEntityManager($this->entityManager);
        }

        $this->dispatcher->dispatch($action, $event);

        return $event;
    }
}
