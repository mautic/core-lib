<?php

namespace Mautic\LeadBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Entity\TagRepository;
use Mautic\LeadBundle\Event\TagEvent;
use Mautic\LeadBundle\Event\TagMergeEvent;
use Mautic\LeadBundle\Form\Type\TagEntityType;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @extends FormModel<Tag>
 */
class TagModel extends FormModel
{
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

        $conn = $this->em->getConnection();
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

        $event = new TagMergeEvent($mainTag, $secTag);
        $this->dispatcher->dispatch($event, LeadEvents::TAG_PRE_MERGE);

        $this->saveEntity($mainTag, false);

        $this->dispatcher->dispatch($event, LeadEvents::TAG_POST_MERGE);

        $this->deleteEntity($secTag);

        return $mainTag;
    }
}
