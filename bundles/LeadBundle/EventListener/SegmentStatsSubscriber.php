<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Mautic\LeadBundle\Event\GetStatDataEvent;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SegmentStatsSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LEAD_LIST_STAT   => ['getStatsLeadEvents', 0],
        ];
    }

    public function getStatsLeadEvents(GetStatDataEvent $event): void
    {
        $result = array_merge(
            $this->getCampaignEntryPoints(),
            $this->getEmailIncludeExcludeList(),
            $this->getCampaignChangeSegmentAction(),
            $this->getFilterSegmentsAction()
        );

        $allSegments = $this->getAllSegments();

        $stats = array_map(function ($data) use ($result) {
            if (array_filter($result, function ($res) use ($data) {
                return $res['item_id'] === $data['item_id'];
            })) {
                $data['is_used'] = 1;
            }

            return $data;
        }, $allSegments);

        $event->addResult('segments', $stats);
    }

    /**
     * @return mixed
     */
    private function getAllSegments()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('title', 'title');
        $rsm->addScalarResult('item_id', 'item_id');
        $rsm->addScalarResult('is_published', 'is_published');
        $query = $this->entityManager->createNativeQuery('SELECT 
            ll.name as title, 
            ll.id as item_id,
            ll.is_published as is_published
            FROM '.MAUTIC_TABLE_PREFIX.'lead_lists ll', $rsm);

        return $query->getResult();
    }

    /**
     * @return mixed
     */
    private function getCampaignEntryPoints()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('title', 'title');
        $rsm->addScalarResult('item_id', 'item_id');

        $query = $this->entityManager->createNativeQuery('SELECT 
            ll.name as title, 
            ll.id as item_id
        FROM '.MAUTIC_TABLE_PREFIX.'campaign_leadlist_xref cl
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists ll on ll.id=cl.leadlist_id
            GROUP BY ll.id', $rsm);

        return $query->getResult();
    }

    /**
     * @return mixed
     */
    private function getEmailIncludeExcludeList()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('title', 'title');
        $rsm->addScalarResult('item_id', 'item_id');

        $query = $this->entityManager->createNativeQuery('SELECT 
            ll.name as title, 
            ll.id as item_id
        FROM '.MAUTIC_TABLE_PREFIX.'email_list_xref eli
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists ll on ll.id=eli.leadlist_id
            GROUP BY ll.id', $rsm);

        $included = $query->getResult();

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('title', 'title');
        $rsm->addScalarResult('item_id', 'item_id');

        $query = $this->entityManager->createNativeQuery('SELECT 
            ll.name as title, 
            ll.id as item_id
        FROM '.MAUTIC_TABLE_PREFIX.'email_list_excluded ele
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists ll on ll.id=ele.leadlist_id
            GROUP BY ll.id', $rsm);

        $excluded = $query->getResult();

        return array_merge($included, $excluded);
    }

    /**
     * @return mixed
     */
    private function getCampaignChangeSegmentAction()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('properties', 'properties');

        $query = $this->entityManager->createNativeQuery('SELECT 
            properties 
        FROM '.MAUTIC_TABLE_PREFIX.'campaign_events ce 
        WHERE ce.type = \'lead.changelist\'', $rsm);

        $segmentIds = [];
        foreach ($query->getResult() as $property) {
            $property       = unserialize($property['properties']);
            $segmentIds     = array_merge($property['addToLists'], $property['removeFromLists'], $segmentIds);
        }

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('title', 'title');
        $rsm->addScalarResult('item_id', 'item_id');

        $query = $this->entityManager->createNativeQuery('SELECT 
            ll.name as title, 
            ll.id as item_id
            FROM '.MAUTIC_TABLE_PREFIX.'lead_lists ll
            WHERE ll.id IN (?)', $rsm);
        $query->setParameter(1, $segmentIds);

        return $query->getResult();
    }

    /**
     * @return mixed
     */
    private function getFilterSegmentsAction()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('filters', 'filters');

        $query = $this->entityManager->createNativeQuery('SELECT 
            filters 
        FROM '.MAUTIC_TABLE_PREFIX.'lead_lists', $rsm);

        $childSegmentIds = [];

        foreach ($query->getResult() as $rowFilters) {
            $segmentMembershipFilters = array_filter(
                unserialize($rowFilters['filters']),
                function (array $filter) {
                    return 'leadlist' === $filter['type'];
                }
            );

            foreach ($segmentMembershipFilters as $filter) {
                foreach ($filter['properties']['filter'] as $childSegmentId) {
                    $childSegmentIds[] = (int) $childSegmentId;
                }
            }
        }

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('title', 'title');
        $rsm->addScalarResult('item_id', 'item_id');

        $query = $this->entityManager->createNativeQuery('SELECT 
            ll.name as title, 
            ll.id as item_id
            FROM '.MAUTIC_TABLE_PREFIX.'lead_lists ll
            WHERE ll.id IN (?)', $rsm);
        $query->setParameter(1, $childSegmentIds);

        return $query->getResult();
    }
}
