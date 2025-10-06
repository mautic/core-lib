<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\EventListener;

use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\LeadBundle\Event\ListTypeaheadEvent;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SegmentFilterTypeaheadSubscriber implements EventSubscriberInterface
{
    public function __construct(private ModelFactory $modelFactory)
    {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ListTypeaheadEvent::class => [
                ['onSegmentFilterAliasEmpty', 1],
                ['onSegmentFilterAliasUser', 0],
                ['onSegmentFilterFieldEmpty', 0],
                ['onSegmentFilterCanProvideTypeahead', 0],
                ['onSegmentFilterEntityByAlias', 0],
            ],
        ];
    }

    public function onSegmentFilterAliasEmpty(ListTypeaheadEvent $event): void
    {
        if (!empty($event->getFieldAlias())) {
            return;
        }

        $dataArray['error']   = 'Alias cannot be empty';
        $dataArray['success'] = 0;

        $event->setDataArray($dataArray);
        $event->stopPropagation();
    }

    public function onSegmentFilterAliasUser(ListTypeaheadEvent $event): void
    {
        if ('owner_id' !== $event->getFieldAlias()) {
            return;
        }

        /** @var LeadModel $leadModel */
        $leadModel = $this->modelFactory->getModel('lead.lead');
        $results   = $leadModel->getLookupResults('user', $event->getFilter());

        $dataArray = [];
        foreach ($results as $r) {
            $name        = $r['firstName'].' '.$r['lastName'];
            $dataArray[] = [
                'value' => $name,
                'id'    => $r['id'],
            ];
        }

        $event->setDataArray($dataArray);
        $event->stopPropagation();
    }

    public function onSegmentFilterFieldEmpty(ListTypeaheadEvent $event): void
    {
        $fieldModel = $this->modelFactory->getModel('lead.field');
        $field      = $fieldModel->getEntityByAlias($event->getFieldAlias());

        if (!empty($field)) {
            return;
        }

        $event->stopPropagation();
    }

    public function onSegmentFilterCanProvideTypeahead(ListTypeaheadEvent $event): void
    {
        $fieldModel = $this->modelFactory->getModel('lead.field');
        $field      = $fieldModel->getEntityByAlias($event->getFieldAlias());

        // Select field types that make sense to provide typeahead for.
        $isLookup     = in_array($field->getType(), ['lookup']);
        $shouldLookup = in_array($field->getAlias(), ['city', 'company', 'title']);

        if ($isLookup && $shouldLookup) {
            return;
        }

        $event->stopPropagation();
    }

    public function onSegmentFilterEntityByAlias(ListTypeaheadEvent $event): void
    {
        $fieldAlias = $event->getFieldAlias();
        $filter     = $event->getFilter();

        /** @var FieldModel $fieldModel */
        $fieldModel = $this->modelFactory->getModel('lead.field');
        $field      = $fieldModel->getEntityByAlias($fieldAlias);

        $dataArray = [];
        if ('lookup' === $field->getType() && !empty($field->getProperties()['list'])) {
            foreach ($field->getProperties()['list'] as $predefinedValue) {
                $dataArray[] = ['value' => $predefinedValue];
            }
        }

        /** @var CompanyModel $companyModel */
        $companyModel = $this->modelFactory->getModel('lead.company');
        if ('company' === $field->getObject()) {
            $results = $companyModel->getLookupResults('companyfield', [$fieldAlias, $filter]);
            foreach ($results as $r) {
                $dataArray[] = ['value' => $r['label']];
            }
        } elseif ('lead' === $field->getObject()) {
            $results = $fieldModel->getLookupResults($fieldAlias, $filter);
            foreach ($results as $r) {
                $dataArray[] = ['value' => $r[$fieldAlias]];
            }
        }

        $event->setDataArray($dataArray);
        $event->stopPropagation();
    }
}
