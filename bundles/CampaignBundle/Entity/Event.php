<?php

namespace Mautic\CampaignBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\UuidInterface;
use Mautic\CoreBundle\Entity\UuidTrait;
use Mautic\CoreBundle\Validator\EntityEvent;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Event.
 *
 * @ApiResource(
 *   attributes={
 *     "security"="false",
 *     "normalization_context"={
 *       "groups"={
 *         "event:read"
 *        },
 *       "swagger_definition_name"="Read"
 *     },
 *     "denormalization_context"={
 *       "groups"={
 *         "event:write"
 *       },
 *       "swagger_definition_name"="Write"
 *     }
 *   }
 * )
 */
class Event implements ChannelInterface, UuidInterface
{
    use UuidTrait;

    public const TABLE_NAME = 'campaign_events';

    public const TYPE_DECISION  = 'decision';
    public const TYPE_ACTION    = 'action';
    public const TYPE_CONDITION = 'condition';

    public const PATH_INACTION = 'no';
    public const PATH_ACTION   = 'yes';

    public const TRIGGER_MODE_DATE      = 'date';
    public const TRIGGER_MODE_INTERVAL  = 'interval';
    public const TRIGGER_MODE_IMMEDIATE = 'immediate';

    public const CHANNEL_EMAIL = 'email';

    /**
     * @var int
     * @Groups({"event:read", "campaign:read"})
     */
    private $id;

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $name;

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $description;

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $type;

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $eventType;

    /**
     * @var int
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $order = 0;

    /**
     * @var array
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $properties = [];

    /**
     * @var \DateTime|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerDate;

    /**
     * @var int
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerInterval = 0;

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerIntervalUnit;

    /**
     * @var \DateTime|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerHour;

    /**
     * @var \DateTime|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerRestrictedStartHour;

    /**
     * @var \DateTime|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerRestrictedStopHour;

    /**
     * @var array|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerRestrictedDaysOfWeek = [];

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $triggerMode;

    /**
     * @var Campaign
     * @Groups({"event:write"})
     */
    private $campaign;

    /**
     * @var ArrayCollection
     **/
    private $children;

    /**
     * @var Event
     **/
    private $parent;

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     **/
    private $decisionPath;

    /**
     * @var string
     * @Groups({"event:read", "event:write", "campaign:read"})
     **/
    private $tempId;

    /**
     * @var ArrayCollection
     * @Groups("event:read")
     */
    private $log;

    /**
     * Used by API to house contact specific logs.
     *
     * @var array
     * @Groups({"event:read", "event:write"})
     */
    private $contactLog = [];

    /**
     * @var string|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $channel;

    /**
     * @var string|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $channelId;

    /**
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $changes = [];

    /**
     * @var int
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $failedCount = 0;

    /**
     * @var \DateTime|null
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private $deleted;

    /**
     * @Groups({"event:read", "event:write", "campaign:read"})
     */
    private ?Event $redirectEvent;

    public function __construct()
    {
        $this->log             = new ArrayCollection();
        $this->children        = new ArrayCollection();
        $this->redirectEvent   = null;
    }

    /**
     * Clean up after clone.
     */
    public function __clone()
    {
        $this->tempId          = null;
        $this->campaign        = null;
        $this->channel         = null;
        $this->channelId       = null;
        $this->redirectEvent   = null;
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass('Mautic\CampaignBundle\Entity\EventRepository')
            ->addIndex(['type', 'event_type'], 'campaign_event_search')
            ->addIndex(['event_type'], 'campaign_event_type')
            ->addIndex(['channel', 'channel_id'], 'campaign_event_channel');

        $builder->addIdColumns();

        $builder->createField('type', 'string')
            ->length(50)
            ->build();

        $builder->createField('eventType', 'string')
            ->columnName('event_type')
            ->length(50)
            ->build();

        $builder->createField('order', 'integer')
            ->columnName('event_order')
            ->build();

        $builder->addField('properties', 'array');

        $builder->addNullableField('deleted', 'datetime');

        $builder->createManyToOne('redirectEvent', 'Event')
            ->cascadePersist()
            ->addJoinColumn('redirect_event_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->createField('triggerDate', 'datetime')
            ->columnName('trigger_date')
            ->nullable()
            ->build();

        $builder->createField('triggerInterval', 'integer')
            ->columnName('trigger_interval')
            ->nullable()
            ->build();

        $builder->createField('triggerIntervalUnit', 'string')
            ->columnName('trigger_interval_unit')
            ->length(1)
            ->nullable()
            ->build();

        $builder->createField('triggerHour', 'time')
            ->columnName('trigger_hour')
            ->nullable()
            ->build();

        $builder->createField('triggerRestrictedStartHour', 'time')
            ->columnName('trigger_restricted_start_hour')
            ->nullable()
            ->build();

        $builder->createField('triggerRestrictedStopHour', 'time')
            ->columnName('trigger_restricted_stop_hour')
            ->nullable()
            ->build();

        $builder->createField('triggerRestrictedDaysOfWeek', 'array')
            ->columnName('trigger_restricted_dow')
            ->nullable()
            ->build();

        $builder->createField('triggerMode', 'string')
            ->columnName('trigger_mode')
            ->length(10)
            ->nullable()
            ->build();

        $builder->createManyToOne('campaign', 'Campaign')
            ->inversedBy('events')
            ->addJoinColumn('campaign_id', 'id', false, false, 'CASCADE')
            ->build();

        $builder->createOneToMany('children', 'Event')
            ->setIndexBy('id')
            ->setOrderBy(['order' => 'ASC'])
            ->mappedBy('parent')
            ->build();

        $builder->createManyToOne('parent', 'Event')
            ->inversedBy('children')
            ->cascadePersist()
            ->addJoinColumn('parent_id', 'id')
            ->build();

        $builder->createField('decisionPath', 'string')
            ->columnName('decision_path')
            ->nullable()
            ->build();

        $builder->createField('tempId', 'string')
            ->columnName('temp_id')
            ->nullable()
            ->build();

        $builder->createOneToMany('log', 'LeadEventLog')
            ->mappedBy('event')
            ->cascadePersist()
            ->fetchExtraLazy()
            ->build();

        $builder->createField('channel', 'string')
            ->nullable()
            ->build();

        $builder->createField('channelId', Types::STRING)
            ->columnName('channel_id')
            ->length(64)
            ->nullable()
            ->build();

        $builder->createField('failedCount', 'integer')
            ->columnName('failed_count')
            ->build();

        static::addUuidField($builder);
    }

    /**
     * Prepares the metadata for API usage.
     *
     * @param $metadata
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata)
    {
        $metadata->setGroupPrefix('campaignEvent')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'description',
                    'type',
                    'eventType',
                    'channel',
                    'channelId',
                ]
            )
            ->addProperties(
                [
                    'order',
                    'properties',
                    'triggerDate',
                    'triggerInterval',
                    'triggerIntervalUnit',
                    'triggerHour',
                    'triggerRestrictedStartHour',
                    'triggerRestrictedStopHour',
                    'triggerRestrictedDaysOfWeek',
                    'triggerMode',
                    'decisionPath',
                    'channel',
                    'channelId',
                    'parent',
                    'children',
                ]
            )
            ->setMaxDepth(1, 'parent')
            ->setMaxDepth(1, 'children')

            // Add standalone groups
            ->setGroupPrefix('campaignEventStandalone')
             ->addListProperties(
                 [
                     'id',
                     'name',
                     'description',
                     'type',
                     'eventType',
                     'channel',
                     'channelId',
                 ]
             )
             ->addProperties(
                 [
                     'campaign',
                     'order',
                     'properties',
                     'triggerDate',
                     'triggerInterval',
                     'triggerIntervalUnit',
                     'triggerHour',
                    'triggerRestrictedStartHour',
                    'triggerRestrictedStopHour',
                    'triggerRestrictedDaysOfWeek',
                     'triggerMode',
                     'children',
                     'parent',
                     'decisionPath',
                 ]
             )

            // Include logs
            ->setGroupPrefix('campaignEventWithLogs')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'description',
                    'type',
                    'eventType',
                    'contactLog',
                    'triggerDate',
                    'triggerInterval',
                    'triggerIntervalUnit',
                    'triggerHour',
                    'triggerRestrictedStartHour',
                    'triggerRestrictedStopHour',
                    'triggerRestrictedDaysOfWeek',
                    'triggerMode',
                    'decisionPath',
                    'order',
                    'parent',
                    'channel',
                    'channelId',
                ]
            )
            ->addProperties(
                [
                    'campaign',
                ]
            )
             ->build();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addConstraint(new EntityEvent());
    }

    /**
     * @param string $prop
     * @param mixed  $val
     */
    private function isChanged($prop, $val)
    {
        $getter  = 'get'.ucfirst($prop);
        $current = $this->$getter();
        if ('category' == $prop || 'parent' == $prop) {
            $currentId = ($current) ? $current->getId() : '';
            $newId     = ($val) ? $val->getId() : null;
            if ($currentId != $newId) {
                $this->changes[$prop] = [$currentId, $newId];
            }
        } elseif ($this->$prop != $val) {
            $this->changes[$prop] = [$this->$prop, $val];
        }
    }

    /**
     * @return array
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function nullId()
    {
        $this->id = null;
    }

    /**
     * Set order.
     *
     * @param int $order
     *
     * @return Event
     */
    public function setOrder($order)
    {
        $this->isChanged('order', $order);

        $this->order = $order;

        return $this;
    }

    /**
     * Get order.
     *
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set properties.
     *
     * @param array $properties
     *
     * @return Event
     */
    public function setProperties($properties)
    {
        $this->isChanged('properties', $properties);

        $this->properties = $properties;

        return $this;
    }

    /**
     * Get properties.
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Set campaign.
     *
     * @return Event
     */
    public function setCampaign(Campaign $campaign)
    {
        $this->campaign = $campaign;

        return $this;
    }

    /**
     * Get campaign.
     *
     * @return \Mautic\CampaignBundle\Entity\Campaign
     */
    public function getCampaign()
    {
        return $this->campaign;
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return Event
     */
    public function setType($type)
    {
        $this->isChanged('type', $type);
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function convertToArray()
    {
        return get_object_vars($this);
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return Event
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
     * @return Event
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
     * Add log.
     *
     * @return Event
     */
    public function addLog(LeadEventLog $log)
    {
        $this->log[] = $log;

        return $this;
    }

    /**
     * Remove log.
     */
    public function removeLog(LeadEventLog $log)
    {
        $this->log->removeElement($log);
    }

    /**
     * Get log.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Get log for a contact and a rotation.
     *
     * @param $rotation
     *
     * @return LeadEventLog|null
     */
    public function getLogByContactAndRotation(Contact $contact, $rotation)
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('lead', $contact))
            ->andWhere(Criteria::expr()->eq('rotation', $rotation))
            ->setMaxResults(1);

        $log = $this->getLog()->matching($criteria);

        if (count($log)) {
            return $log->first();
        }

        return null;
    }

    /**
     * Add children.
     *
     * @param \Mautic\CampaignBundle\Entity\Event $children
     *
     * @return Event
     */
    public function addChild(Event $children)
    {
        $this->children[] = $children;

        return $this;
    }

    /**
     * Remove children.
     *
     * @param \Mautic\CampaignBundle\Entity\Event $children
     */
    public function removeChild(Event $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * @return ArrayCollection|Event[]
     */
    public function getChildren()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->isNull('deleted'));

        return $this->children->matching($criteria);
    }

    /**
     * @return ArrayCollection|Event[]
     */
    public function getPositiveChildren()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('decisionPath', self::PATH_ACTION));

        return $this->getChildren()->matching($criteria);
    }

    /**
     * @return ArrayCollection|Event[]
     */
    public function getNegativeChildren()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('decisionPath', self::PATH_INACTION));

        return $this->getChildren()->matching($criteria);
    }

    /**
     * @param $type
     *
     * @return ArrayCollection
     */
    public function getChildrenByType($type)
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('type', $type));

        return $this->getChildren()->matching($criteria);
    }

    /**
     * @param $type
     *
     * @return ArrayCollection
     */
    public function getChildrenByEventType($type)
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('eventType', $type));

        return $this->getChildren()->matching($criteria);
    }

    /**
     * Set parent.
     *
     * @param \Mautic\CampaignBundle\Entity\Event $parent
     *
     * @return Event
     */
    public function setParent(Event $parent = null)
    {
        $this->isChanged('parent', $parent);
        $this->parent = $parent;

        return $this;
    }

    /**
     * Remove parent.
     */
    public function removeParent()
    {
        $this->isChanged('parent', '');
        $this->parent = null;
    }

    /**
     * Get parent.
     *
     * @return \Mautic\CampaignBundle\Entity\Event|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return mixed
     */
    public function getTriggerDate()
    {
        return $this->triggerDate;
    }

    /**
     * @param mixed $triggerDate
     */
    public function setTriggerDate($triggerDate = 'now')
    {
        if (is_array($triggerDate) && array_key_exists('date', $triggerDate)) {
            $triggerDate = new \DateTime($triggerDate['date']);
        } elseif (is_string($triggerDate)) {
            $triggerDate = new \DateTime($triggerDate);
        }

        $this->isChanged('triggerDate', $triggerDate);
        $this->triggerDate = $triggerDate;
    }

    /**
     * @return int
     */
    public function getTriggerInterval()
    {
        return $this->triggerInterval;
    }

    /**
     * @param int $triggerInterval
     */
    public function setTriggerInterval($triggerInterval)
    {
        $this->isChanged('triggerInterval', $triggerInterval);
        $this->triggerInterval = $triggerInterval;
    }

    /**
     * @return \DateTime
     */
    public function getTriggerHour()
    {
        return $this->triggerHour;
    }

    /**
     * @param string|array $triggerHour
     *
     * @return Event
     */
    public function setTriggerHour($triggerHour)
    {
        if (empty($triggerHour)) {
            $triggerHour = null;
        } elseif (is_array($triggerHour) && array_key_exists('date', $triggerHour)) {
            $triggerHour = new \DateTime($triggerHour['date']);
        } elseif (is_string($triggerHour)) {
            $triggerHour = new \DateTime($triggerHour);
        }

        $this->isChanged('triggerHour', $triggerHour ? $triggerHour->format('H:i') : $triggerHour);
        $this->triggerHour = $triggerHour;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTriggerIntervalUnit()
    {
        return $this->triggerIntervalUnit;
    }

    /**
     * @param mixed $triggerIntervalUnit
     */
    public function setTriggerIntervalUnit($triggerIntervalUnit)
    {
        $this->isChanged('triggerIntervalUnit', $triggerIntervalUnit);
        $this->triggerIntervalUnit = $triggerIntervalUnit;
    }

    /**
     * @return mixed
     */
    public function getEventType()
    {
        return $this->eventType;
    }

    /**
     * @param $eventType
     *
     * @return $this
     */
    public function setEventType($eventType)
    {
        $this->isChanged('eventType', $eventType);
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTriggerMode()
    {
        return $this->triggerMode;
    }

    /**
     * @param mixed $triggerMode
     */
    public function setTriggerMode($triggerMode)
    {
        $this->isChanged('triggerMode', $triggerMode);
        $this->triggerMode = $triggerMode;
    }

    /**
     * @return mixed
     */
    public function getDecisionPath()
    {
        return $this->decisionPath;
    }

    /**
     * @param mixed $decisionPath
     */
    public function setDecisionPath($decisionPath)
    {
        $this->isChanged('decisionPath', $decisionPath);
        $this->decisionPath = $decisionPath;
    }

    /**
     * @return mixed
     */
    public function getTempId()
    {
        return $this->tempId;
    }

    /**
     * @param mixed $tempId
     */
    public function setTempId($tempId)
    {
        $this->isChanged('tempId', $tempId);
        $this->tempId = $tempId;
    }

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     */
    public function setChannel($channel)
    {
        $this->isChanged('channel', $channel);
        $this->channel = $channel;
    }

    /**
     * @return string
     */
    public function getChannelId()
    {
        return $this->channelId;
    }

    /**
     * @param string $channelId
     */
    public function setChannelId($channelId)
    {
        $this->isChanged('channelId', $channelId);
        $this->channelId = (string) $channelId;
    }

    /**
     * Used by the API.
     *
     * @return LeadEventLog[]|\Doctrine\Common\Collections\Collection|static
     */
    public function getContactLog(Contact $contact = null)
    {
        if ($this->contactLog) {
            return $this->contactLog;
        }

        return $this->log->matching(
            Criteria::create()
                    ->where(
                        Criteria::expr()->eq('lead', $contact)
                    )
        );
    }

    /**
     * Used by the API.
     *
     * @param array $contactLog
     *
     * @return Event
     */
    public function setContactLog($contactLog)
    {
        $this->contactLog = $contactLog;

        return $this;
    }

    /**
     * Used by the API.
     *
     * @return Event
     */
    public function addContactLog($contactLog)
    {
        $this->contactLog[] = $contactLog;

        return $this;
    }

    /**
     * Get the value of triggerRestrictedStartHour.
     *
     * @return \DateTime|null
     */
    public function getTriggerRestrictedStartHour()
    {
        return $this->triggerRestrictedStartHour;
    }

    /**
     * Set the value of triggerRestrictedStartHour.
     *
     * @param array<string, string>|string|null $triggerRestrictedStartHour
     *
     * @return $this
     */
    public function setTriggerRestrictedStartHour($triggerRestrictedStartHour)
    {
        if (empty($triggerRestrictedStartHour)) {
            $triggerRestrictedStartHour = null;
        } elseif (is_array($triggerRestrictedStartHour) && array_key_exists('date', $triggerRestrictedStartHour)) {
            $triggerRestrictedStartHour = new \DateTime($triggerRestrictedStartHour['date']);
        } elseif (is_string($triggerRestrictedStartHour)) {
            $triggerRestrictedStartHour = new \DateTime($triggerRestrictedStartHour);
        }

        $this->isChanged('triggerRestrictedStartHour', $triggerRestrictedStartHour ? $triggerRestrictedStartHour->format('H:i') : $triggerRestrictedStartHour);

        $this->triggerRestrictedStartHour = $triggerRestrictedStartHour;

        return $this;
    }

    /**
     * Get the value of triggerRestrictedStopHour.
     *
     * @return \DateTime|null
     */
    public function getTriggerRestrictedStopHour()
    {
        return $this->triggerRestrictedStopHour;
    }

    /**
     * Set the value of triggerRestrictedStopHour.
     *
     * @param array<string, string>|string|null $triggerRestrictedStopHour
     *
     * @return $this
     */
    public function setTriggerRestrictedStopHour($triggerRestrictedStopHour)
    {
        if (empty($triggerRestrictedStopHour)) {
            $triggerRestrictedStopHour = null;
        } elseif (is_array($triggerRestrictedStopHour) && array_key_exists('date', $triggerRestrictedStopHour)) {
            $triggerRestrictedStopHour = new \DateTime($triggerRestrictedStopHour['date']);
        } elseif (is_string($triggerRestrictedStopHour)) {
            $triggerRestrictedStopHour = new \DateTime($triggerRestrictedStopHour);
        }

        $this->isChanged('triggerRestrictedStopHour', $triggerRestrictedStopHour ? $triggerRestrictedStopHour->format('H:i') : $triggerRestrictedStopHour);

        $this->triggerRestrictedStopHour = $triggerRestrictedStopHour;

        return $this;
    }

    /**
     * Get the value of triggerRestrictedDaysOfWeek.
     *
     * @return array
     */
    public function getTriggerRestrictedDaysOfWeek()
    {
        return (array) $this->triggerRestrictedDaysOfWeek;
    }

    /**
     * Set the value of triggerRestrictedDaysOfWeek.
     *
     * @return self
     */
    public function setTriggerRestrictedDaysOfWeek(array $triggerRestrictedDaysOfWeek = null)
    {
        $this->triggerRestrictedDaysOfWeek = $triggerRestrictedDaysOfWeek;
        $this->isChanged('triggerRestrictedDaysOfWeek', $triggerRestrictedDaysOfWeek);

        return $this;
    }

    /**
     * @return ?int
     */
    public function getFailedCount()
    {
        return $this->failedCount;
    }

    public function setDeleted($deleted = 'now'): Event
    {
        if (is_array($deleted) && array_key_exists('date', $deleted)) {
            $deleted = new \DateTime($deleted['date']);
        } elseif (is_string($deleted)) {
            $deleted = new \DateTime($deleted);
        }

        $this->isChanged('deleted', $deleted);
        $this->deleted = $deleted;

        return $this;
    }

    public function getDeleted(): ?\DateTimeInterface
    {
        return $this->deleted;
    }

    public function isDeleted(): bool
    {
        return !is_null($this->deleted);
    }

    public function setRedirectEvent(?Event $redirectEvent = null): Event
    {
        $this->isChanged('redirectEvent', $redirectEvent);
        $this->redirectEvent = $redirectEvent;

        return $this;
    }

    public function getRedirectEvent(): ?Event
    {
        return $this->redirectEvent;
    }

    public function shouldBeRedirected(): bool
    {
        return $this->isDeleted() && null !== $this->redirectEvent;
    }
}
