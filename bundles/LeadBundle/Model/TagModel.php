<?php

namespace Mautic\LeadBundle\Model;

use Mautic\CampaignBundle\Entity\Event as CampaignEvent;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\FormBundle\Entity\Action;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Entity\TagRepository;
use Mautic\LeadBundle\Event\TagEvent;
use Mautic\LeadBundle\Event\TagMergeEvent;
use Mautic\LeadBundle\Form\Type\TagEntityType;
use Mautic\LeadBundle\LeadEvents;
use Mautic\PointBundle\Entity\TriggerEvent;
use Mautic\ReportBundle\Entity\Report;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @extends FormModel<Tag>
 */
class TagModel extends FormModel
{
    /**
     * @var array<int, string>
     */
    private const TAG_PROPERTY_KEYS = [
        'add_tags',
        'remove_tags',
        'tags',
    ];

    /**
     * @return TagRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository(Tag::class);
    }

    public function getPermissionBase(): string
    {
        return 'lead:leads';
    }

    /**
     * Get a specific entity or generate a new one if id is empty.
     *
     * @param int $id
     */
    public function getEntity($id = null): ?Tag
    {
        if (is_null($id)) {
            return new Tag();
        }

        return parent::getEntity($id);
    }

    /**
     * @param Tag   $entity
     * @param array $options
     */
    public function createForm($entity, FormFactoryInterface $formFactory, $action = null, $options = []): \Symfony\Component\Form\FormInterface
    {
        if (!$entity instanceof Tag) {
            throw new MethodNotAllowedHttpException(['Tag']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(TagEntityType::class, $entity, $options);
    }

    /**
     * @throws MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, ?Event $event = null): ?Event
    {
        if (!$entity instanceof Tag) {
            throw new MethodNotAllowedHttpException(['Tag']);
        }

        switch ($action) {
            case 'pre_save':
                $name = LeadEvents::TAG_PRE_SAVE;
                break;
            case 'post_save':
                $name = LeadEvents::TAG_POST_SAVE;
                break;
            case 'pre_delete':
                $name = LeadEvents::TAG_PRE_DELETE;
                break;
            case 'post_delete':
                $name = LeadEvents::TAG_POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new TagEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }

            $this->dispatcher->dispatch($event, $name);

            return $event;
        }

        return null;
    }

    public function tagMerge(Tag $mainTag, Tag $secTag): Tag
    {
        $this->logger->debug('TAG: Merging tags');

        if ($mainTag->getId() === $secTag->getId()) {
            return $mainTag;
        }

        $conn    = $this->em->getConnection();
        $leadIds = $conn->createQueryBuilder()
            ->select('lead_id')
            ->from(MAUTIC_TABLE_PREFIX.'lead_tags_xref', 'ltx')
            ->where('ltx.tag_id = :id')
            ->setParameter('id', $secTag->getId())
            ->executeQuery()
            ->fetchFirstColumn();

        if ($leadIds) {
            $repo = $this->getRepository();
            $repo->addTagsToLeads($leadIds, [$mainTag->getId()]);
            $repo->removeTagsFromLeads($leadIds, [$secTag->getId()]);
        }

        $this->replaceMergedTagReferences($mainTag, $secTag);

        $event = new TagMergeEvent($mainTag, $secTag);
        $this->dispatcher->dispatch($event, LeadEvents::TAG_PRE_MERGE);

        $this->saveEntity($mainTag, false);

        $this->dispatcher->dispatch($event, LeadEvents::TAG_POST_MERGE);

        $this->deleteEntity($secTag);

        return $mainTag;
    }

    private function replaceMergedTagReferences(Tag $mainTag, Tag $secTag): void
    {
        $mainTagId = (int) $mainTag->getId();
        $secTagId  = (int) $secTag->getId();

        $this->replaceTagPropertiesInEntities(
            $this->em->getRepository(CampaignEvent::class)->findBy(['type' => ['lead.changetags', 'lead.tags']]),
            $mainTag,
            $secTag,
        );

        $this->replaceTagPropertiesInEntities(
            $this->em->getRepository(Action::class)->findBy(['type' => 'lead.changetags']),
            $mainTag,
            $secTag,
        );

        $this->replaceTagPropertiesInEntities(
            $this->em->getRepository(TriggerEvent::class)->findBy(['type' => 'lead.changetags']),
            $mainTag,
            $secTag,
        );

        $this->replaceTagFiltersInSegments($mainTagId, $secTagId);
        $this->replaceTagFiltersInReports($mainTagId, $secTagId);
    }

    /**
     * @param iterable<CampaignEvent|Action|TriggerEvent> $entities
     */
    private function replaceTagPropertiesInEntities(iterable $entities, Tag $mainTag, Tag $secTag): void
    {
        foreach ($entities as $entity) {
            $properties = $entity->getProperties();
            $updated    = $this->replaceTagValuesInConfiguredProperties($properties, $secTag->getTag(), $mainTag->getTag());

            if ($entity instanceof CampaignEvent) {
                $updated = $this->replaceTagIdsInNestedProperties($updated, (int) $secTag->getId(), (int) $mainTag->getId());
            }

            if ($updated === $properties) {
                continue;
            }

            $entity->setProperties($updated);
            $this->em->persist($entity);
        }
    }

    private function replaceTagFiltersInSegments(int $mainTagId, int $secTagId): void
    {
        /** @var LeadList $segment */
        foreach ($this->em->getRepository(LeadList::class)->createQueryBuilder('l')
            ->where('l.filters LIKE :tagFilter')
            ->setParameter('tagFilter', '%"tags"%')
            ->getQuery()
            ->getResult() as $segment) {
            $filters = $segment->getFilters();
            $updated = $this->replaceTagValuesInSegmentFilters($filters, $secTagId, $mainTagId);

            if ($updated === $filters) {
                continue;
            }

            $segment->setFilters($updated);
            $this->em->persist($segment);
        }
    }

    private function replaceTagFiltersInReports(int $mainTagId, int $secTagId): void
    {
        /** @var Report $report */
        foreach ($this->em->getRepository(Report::class)->createQueryBuilder('r')
            ->where('r.filters LIKE :tagFilter')
            ->setParameter('tagFilter', '%tag"%')
            ->getQuery()
            ->getResult() as $report) {
            $filters = $report->getFilters();
            $updated = $this->replaceTagValuesInReportFilters($filters, $secTagId, $mainTagId);

            if ($updated === $filters) {
                continue;
            }

            $report->setFilters($updated);
            $this->em->persist($report);
        }
    }

    /**
     * @param array<string|int, mixed> $properties
     *
     * @return array<string|int, mixed>
     */
    private function replaceTagValuesInConfiguredProperties(array $properties, int|string $oldValue, int|string $newValue): array
    {
        foreach ($properties as $key => $value) {
            if ('properties' === $key) {
                continue;
            }

            if (is_array($value)) {
                $properties[$key] = in_array($key, self::TAG_PROPERTY_KEYS, true)
                    ? $this->replaceTagValues($value, $oldValue, $newValue)
                    : $this->replaceTagValuesInConfiguredProperties($value, $oldValue, $newValue);
            }
        }

        return $properties;
    }

    /**
     * @param array<string|int, mixed> $properties
     *
     * @return array<string|int, mixed>
     */
    private function replaceTagIdsInNestedProperties(array $properties, int $oldTagId, int $newTagId): array
    {
        if (!isset($properties['properties']) || !is_array($properties['properties'])) {
            return $properties;
        }

        foreach (self::TAG_PROPERTY_KEYS as $key) {
            if (!isset($properties['properties'][$key]) || !is_array($properties['properties'][$key])) {
                continue;
            }

            $properties['properties'][$key] = $this->replaceTagValues($properties['properties'][$key], $oldTagId, $newTagId);
        }

        return $properties;
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaceTagValuesInSegmentFilters(array $filters, int $oldTagId, int $newTagId): array
    {
        foreach ($filters as $index => $filter) {
            if ('tags' !== ($filter['type'] ?? null)) {
                continue;
            }

            if (isset($filter['properties']['filter']) && is_array($filter['properties']['filter'])) {
                $filters[$index]['properties']['filter'] = $this->replaceTagValues($filter['properties']['filter'], $oldTagId, $newTagId);
            }

            if (isset($filter['filter']) && is_array($filter['filter'])) {
                $filters[$index]['filter'] = $this->replaceTagValues($filter['filter'], $oldTagId, $newTagId);
            }
        }

        return $filters;
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaceTagValuesInReportFilters(array $filters, int $oldTagId, int $newTagId): array
    {
        foreach ($filters as $index => $filter) {
            if ('tag' !== ($filter['column'] ?? null)) {
                continue;
            }

            if (is_array($filter['value'] ?? null)) {
                $filters[$index]['value'] = $this->replaceTagValues($filter['value'], $oldTagId, $newTagId);
            }
        }

        return $filters;
    }

    /**
     * @param array<int, int|string> $values
     *
     * @return array<int, int|string>
     */
    private function replaceTagValues(array $values, int|string $oldValue, int|string $newValue): array
    {
        $updated = [];
        $seen    = [];

        foreach ($values as $value) {
            if ((string) $value === (string) $oldValue) {
                $value = $newValue;
            }

            $dedupeKey = (string) $value;
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $updated[]        = $value;
        }

        return $updated;
    }
}
