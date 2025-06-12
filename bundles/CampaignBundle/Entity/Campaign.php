<?php

namespace Mautic\CampaignBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Mautic\CoreBundle\Entity\OptimisticLockInterface;
use Mautic\CoreBundle\Entity\OptimisticLockTrait;
use Mautic\CoreBundle\Entity\PublishStatusIconAttributesInterface;
use Mautic\CoreBundle\Entity\UuidInterface;
use Mautic\CoreBundle\Entity\UuidTrait;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\ProjectBundle\Entity\ProjectTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: [
        'groups'                  => ['campaign:read'],
        'swagger_definition_name' => 'Read',
    ],
    denormalizationContext: [
        'groups'                  => ['campaign:write'],
        'swagger_definition_name' => 'Write',
    ]
)]
class Campaign extends FormEntity implements PublishStatusIconAttributesInterface, OptimisticLockInterface, UuidInterface
{
    use UuidTrait;

    use OptimisticLockTrait;
    use ProjectTrait;

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Groups(['campaign:read', 'campaign:write'])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string')]
    #[Groups(['campaign:read', 'campaign:write'])]
    private string $name;

    #[ORM\ManyToOne(targetEntity: 'Mautic\CategoryBundle\Entity\Category')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['campaign:read', 'campaign:write'])]
    private ?Category $category = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Groups(['campaign:read', 'campaign:write'])]
    private ?string $description = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: 'Event', cascade: ['all'], orphanRemoval: true)]
    #[Groups(['campaign:read', 'campaign:write'])]
    private Collection $events;

    /**
     * @var Collection<int, LeadList>
     */
    #[ORM\ManyToMany(targetEntity: 'Mautic\LeadBundle\Entity\LeadList', inversedBy: 'campaigns')]
    #[ORM\JoinTable(name: 'campaign_leadlist_xref')]
    #[Groups(['campaign:read', 'campaign:write'])]
    private Collection $lists;

    /**
     * @var Collection<int, Form>
     */
    #[ORM\ManyToMany(targetEntity: 'Mautic\FormBundle\Entity\Form', inversedBy: 'campaigns')]
    #[ORM\JoinTable(name: 'campaign_form_xref')]
    #[Groups(['campaign:read', 'campaign:write'])]
    private Collection $forms;

    #[ORM\Column(name: 'canvas_settings', type: 'array', nullable: true)]
    #[Groups(['campaign:read', 'campaign:write'])]
    private ?array $canvasSettings = [];

    #[ORM\Column(name: 'allow_restart', type: 'boolean')]
    #[Groups(['campaign:read', 'campaign:write'])]
    private bool $allowRestart = false;

    public const TABLE_NAME = 'campaigns';

    private ?\DateTimeInterface $publishUp = null;

    private ?\DateTimeInterface $publishDown = null;

    public ?\DateTimeInterface $deleted = null;

    /**
     * @var Collection<int, Lead>
     */
    private Collection $leads;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->leads  = new ArrayCollection();
        $this->lists  = new ArrayCollection();
        $this->forms  = new ArrayCollection();
        $this->initializeProjects();
    }

    public function __clone()
    {
        $this->leads  = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->lists  = new ArrayCollection();
        $this->forms  = new ArrayCollection();
        $this->id     = null;

        parent::__clone();
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(CampaignRepository::class);

        $builder->addIdColumns();

        $builder->addPublishDates();

        $builder->addCategory();

        $builder->createOneToMany('events', Event::class)
            ->setIndexBy('id')
            ->setOrderBy(['order' => 'ASC'])
            ->mappedBy('campaign')
            ->cascadeAll()
            ->fetchExtraLazy()
            ->build();

        $builder->createOneToMany('leads', Lead::class)
            ->mappedBy('campaign')
            ->fetchExtraLazy()
            ->build();

        $builder->createManyToMany('lists', LeadList::class)
            ->setJoinTable('campaign_leadlist_xref')
            ->setIndexBy('id')
            ->addInverseJoinColumn('leadlist_id', 'id', false, false, 'CASCADE')
            ->addJoinColumn('campaign_id', 'id', true, false, 'CASCADE')
            ->build();

        $builder->createManyToMany('forms', Form::class)
            ->setJoinTable('campaign_form_xref')
            ->setIndexBy('id')
            ->addInverseJoinColumn('form_id', 'id', false, false, 'CASCADE')
            ->addJoinColumn('campaign_id', 'id', true, false, 'CASCADE')
            ->build();

        $builder->createField('canvasSettings', 'array')
            ->columnName('canvas_settings')
            ->nullable()
            ->build();

        $builder->addNamedField('allowRestart', 'boolean', 'allow_restart');
        $builder->addNullableField('deleted', 'datetime');

        self::addVersionField($builder);
        static::addUuidField($builder);
        self::addProjectsField($builder, 'campaign_projects_xref', 'campaign_id');
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'name',
            new Assert\NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );
    }

    /**
     * Prepares the metadata for API usage.
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('campaign')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'category',
                    'description',
                ]
            )
            ->addProperties(
                [
                    'allowRestart',
                    'publishUp',
                    'publishDown',
                    'events',
                    'forms',
                    'lists', // @deprecated, will be renamed to 'segments' in 3.0.0
                    'canvasSettings',
                ]
            )
            ->setGroupPrefix('campaignBasic')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'description',
                    'allowRestart',
                    'events',
                    'publishUp',
                    'publishDown',
                    'deleted',
                ]
            )
            ->build();

        self::addProjectsInLoadApiMetadata($metadata, 'campaign');
    }

    public function convertToArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param string $prop
     * @param mixed  $val
     */
    protected function isChanged($prop, $val)
    {
        $getter  = 'get'.ucfirst($prop);
        $current = $this->$getter();
        if ('category' == $prop) {
            $currentId = ($current) ? $current->getId() : '';
            $newId     = ($val) ? $val->getId() : null;
            if ($currentId != $newId) {
                $this->changes[$prop] = [$currentId, $newId];
            }
        } else {
            parent::isChanged($prop, $val);
        }
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return Campaign
     */
    public function setDescription($description)
    {
        $this->isChanged('description', $description);
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Campaign
     */
    public function setName($name)
    {
        $this->isChanged('name', $name);
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Calls $this->addEvent on every item in the collection.
     *
     * @return Campaign
     */
    public function addEvents(array $events)
    {
        foreach ($events as $id => $event) {
            $this->addEvent($id, $event);
        }

        return $this;
    }

    /**
     * Add events.
     *
     * @return Campaign
     */
    public function addEvent($key, Event $event)
    {
        if ($changes = $event->getChanges()) {
            $this->changes['events']['added'][$key] = [$key, $changes];
        }
        $this->events[$key] = $event;

        return $this;
    }

    /**
     * Remove events.
     */
    public function removeEvent(Event $event): void
    {
        $this->changes['events']['removed'][$event->getId()] = $event->getName();

        $this->events->removeElement($event);
    }

    /**
     * Get events.
     *
     * @return Collection<int, Event>
     */
    public function getEvents()
    {
        return $this->events;
    }

    public function getRootEvents(): Collection
    {
        $criteria = Criteria::create()->where(
            Criteria::expr()->andX(
                Criteria::expr()->isNull('parent'),
                Criteria::expr()->isNull('deleted')
            )
        );
        $events   = $this->getEvents()->matching($criteria);

        // Doctrine loses the indexBy mapping definition when using matching so we have to manually reset them.
        // @see https://github.com/doctrine/doctrine2/issues/4693
        $keyedArrayCollection = new ArrayCollection();
        /** @var Event $event */
        foreach ($events as $event) {
            $keyedArrayCollection->set($event->getId(), $event);
        }

        unset($events);

        return $keyedArrayCollection;
    }

    public function getInactionBasedEvents(): Collection
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('decisionPath', Event::PATH_INACTION));
        $events   = $this->getEvents()->matching($criteria);

        // Doctrine loses the indexBy mapping definition when using matching so we have to manually reset them.
        // @see https://github.com/doctrine/doctrine2/issues/4693
        $keyedArrayCollection = new ArrayCollection();
        /** @var Event $event */
        foreach ($events as $event) {
            $keyedArrayCollection->set($event->getId(), $event);
        }

        unset($events);

        return $keyedArrayCollection;
    }

    /**
     * @param string $type
     *
     * @return Collection<int,Event>
     */
    public function getEventsByType($type): Collection
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('eventType', $type));
        $events   = $this->getEvents()->matching($criteria);

        // Doctrine loses the indexBy mapping definition when using matching so we have to manually reset them.
        // @see https://github.com/doctrine/doctrine2/issues/4693
        $keyedArrayCollection = new ArrayCollection();
        /** @var Event $event */
        foreach ($events as $event) {
            $keyedArrayCollection->set($event->getId(), $event);
        }

        unset($events);

        return $keyedArrayCollection;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEmailSendEvents(): Collection
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('type', 'email.send'));
        $events   = $this->getEvents()->matching($criteria);

        // Doctrine loses the indexBy mapping definition when using matching so we have to manually reset them.
        // @see https://github.com/doctrine/doctrine2/issues/4693
        $keyedArrayCollection = new ArrayCollection();
        /** @var Event $event */
        foreach ($events as $event) {
            $keyedArrayCollection->set($event->getId(), $event);
        }

        return $keyedArrayCollection;
    }

    public function isEmailCampaign(): bool
    {
        $criteria     = Criteria::create()->where(Criteria::expr()->eq('type', 'email.send'))->setMaxResults(1);
        $emailEvent   = $this->getEvents()->matching($criteria);

        return !$emailEvent->isEmpty();
    }

    /**
     * Set publishUp.
     *
     * @param ?\DateTime $publishUp
     *
     * @return Campaign
     */
    public function setPublishUp($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);
        $this->publishUp = $publishUp;

        return $this;
    }

    /**
     * Get publishUp.
     *
     * @return \DateTimeInterface
     */
    public function getPublishUp()
    {
        return $this->publishUp;
    }

    /**
     * Set publishDown.
     *
     * @param ?\DateTime $publishDown
     *
     * @return Campaign
     */
    public function setPublishDown($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);
        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * Get publishDown.
     *
     * @return \DateTimeInterface
     */
    public function getPublishDown()
    {
        return $this->publishDown;
    }

    /**
     * @return mixed
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param mixed $category
     */
    public function setCategory($category): void
    {
        $this->isChanged('category', $category);
        $this->category = $category;
    }

    /**
     * Add lead.
     *
     * @return Campaign
     */
    public function addLead($key, Lead $lead)
    {
        $action     = ($this->leads->contains($lead)) ? 'updated' : 'added';
        $leadEntity = $lead->getLead();

        $this->changes['leads'][$action][$leadEntity->getId()] = $leadEntity->getPrimaryIdentifier();
        $this->leads[$key]                                     = $lead;

        return $this;
    }

    /**
     * Remove lead.
     */
    public function removeLead(Lead $lead): void
    {
        $leadEntity                                              = $lead->getLead();
        $this->changes['leads']['removed'][$leadEntity->getId()] = $leadEntity->getPrimaryIdentifier();
        $this->leads->removeElement($lead);
    }

    /**
     * Get leads.
     *
     * @return Lead[]|Collection
     */
    public function getLeads()
    {
        return $this->leads;
    }

    /**
     * @return Collection<int, LeadList>
     */
    public function getLists()
    {
        return $this->lists;
    }

    /**
     * Add list.
     *
     * @return Campaign
     */
    public function addList(LeadList $list)
    {
        $this->lists[$list->getId()] = $list;

        $this->changes['lists']['added'][$list->getId()] = $list->getName();

        return $this;
    }

    /**
     * Remove list.
     */
    public function removeList(LeadList $list): void
    {
        $this->changes['lists']['removed'][$list->getId()] = $list->getName();
        $this->lists->removeElement($list);
    }

    /**
     * @return Collection<int, Form>
     */
    public function getForms()
    {
        return $this->forms;
    }

    /**
     * Add form.
     *
     * @return Campaign
     */
    public function addForm(Form $form)
    {
        $this->forms[$form->getId()] = $form;

        $this->changes['forms']['added'][$form->getId()] = $form->getName();

        return $this;
    }

    /**
     * Remove form.
     */
    public function removeForm(Form $form): void
    {
        $this->changes['forms']['removed'][$form->getId()] = $form->getName();
        $this->forms->removeElement($form);
    }

    /**
     * @return mixed
     */
    public function getCanvasSettings()
    {
        return $this->canvasSettings;
    }

    public function setCanvasSettings(array $canvasSettings): void
    {
        $this->canvasSettings = $canvasSettings;
    }

    public function getAllowRestart(): bool
    {
        return (bool) $this->allowRestart;
    }

    public function allowRestart(): bool
    {
        return $this->getAllowRestart();
    }

    /**
     * @param bool $allowRestart
     *
     * @return Campaign
     */
    public function setAllowRestart($allowRestart)
    {
        $allowRestart = (bool) $allowRestart;
        $this->isChanged('allowRestart', $allowRestart);

        $this->allowRestart = $allowRestart;

        return $this;
    }

    public function setDeleted(?\DateTimeInterface $deleted): void
    {
        $this->isChanged('deleted', $deleted);
        $this->deleted = $deleted;
    }

    public function isDeleted(): bool
    {
        return !is_null($this->deleted);
    }

    /**
     * Get contact membership.
     *
     * @return Collection
     */
    public function getContactMembership(Contact $contact)
    {
        return $this->leads->matching(
            Criteria::create()
                ->where(Criteria::expr()->eq('lead', $contact))
                ->orderBy(['dateAdded' => Order::Descending->value])
        );
    }

    public function getOnclickMethod(): string
    {
        return 'Mautic.confirmationCampaignPublishStatus(mQuery(this));';
    }

    public function getDataAttributes(): array
    {
        return [
            'data-toggle'           => 'confirmation',
            'data-confirm-callback' => 'confirmCallbackCampaignPublishStatus',
            'data-cancel-callback'  => 'dismissConfirmation',
        ];
    }

    public function getTranslationKeysDataAttributes(): array
    {
        return [
            'data-message'      => 'mautic.campaign.form.confirmation.message',
            'data-confirm-text' => 'mautic.campaign.form.confirmation.confirm_text',
            'data-cancel-text'  => 'mautic.campaign.form.confirmation.cancel_text',
        ];
    }
}
