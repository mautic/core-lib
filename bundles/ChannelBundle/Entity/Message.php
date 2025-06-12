<?php

namespace Mautic\ChannelBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Mautic\CoreBundle\Entity\UuidInterface;
use Mautic\CoreBundle\Entity\UuidTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidationClassMetadata;

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
        'groups'                  => ['message:read'],
        'swagger_definition_name' => 'Read',
        'api_included'            => ['category', 'channels'],
    ],
    denormalizationContext: [
        'groups'                  => ['message:write'],
        'swagger_definition_name' => 'Write',
    ]
)]
class Message extends FormEntity implements UuidInterface
{
    use UuidTrait;

    /**
     * @var ?int
     */
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Groups(['message:read'])]
    private $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: 'string')]
    #[Groups(['message:read', 'message:write', 'channel:read'])]
    private $name;

    /**
     * @var ?string
     */
    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Groups(['message:read', 'message:write'])]
    private $description;

    /**
     * @var ?\DateTimeInterface
     */
    #[ORM\Column(name: 'publish_up', type: 'datetime', nullable: true)]
    #[Groups(['message:read', 'message:write'])]
    private $publishUp;

    /**
     * @var ?\DateTimeInterface
     */
    #[ORM\Column(name: 'publish_down', type: 'datetime', nullable: true)]
    #[Groups(['message:read', 'message:write'])]
    private $publishDown;

    /**
     * @var ?Category
     */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['message:read', 'message:write'])]
    private $category;

    /**
     * @var ArrayCollection<int,Channel>
     */
    #[ORM\OneToMany(mappedBy: 'message', targetEntity: Channel::class, cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['message:read', 'message:write'])]
    private $channels;

    public function __clone()
    {
        $this->id = null;
    }

    public static function loadMetadata(ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('messages')
                ->setCustomRepositoryClass(MessageRepository::class)
                ->addIndex(['date_added'], 'date_message_added');

        $builder
            ->addIdColumns()
            ->addPublishDates()
            ->addCategory();

        $builder->createOneToMany('channels', Channel::class)
            ->setIndexBy('channel')
            ->orphanRemoval()
            ->mappedBy('message')
            ->cascadeMerge()
            ->cascadePersist()
            ->cascadeDetach()
            ->build();

        static::addUuidField($builder);
    }

    public static function loadValidatorMetadata(ValidationClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('name', new NotBlank([
            'message' => 'mautic.core.name.required',
        ]));
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('message')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'description',
                ]
            )
            ->addProperties(
                [
                    'publishUp',
                    'publishDown',
                    'channels',
                    'category',
                ]
            )
            ->build();
    }

    public function __construct()
    {
        $this->channels = new ArrayCollection();
    }

    /**
     * @return ?int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ?string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param ?string $name
     *
     * @return Message
     */
    public function setName($name)
    {
        $this->isChanged('name', $name);
        $this->name = $name;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param ?string $description
     *
     * @return Message
     */
    public function setDescription($description)
    {
        $this->isChanged('description', $description);
        $this->description = $description;

        return $this;
    }

    /**
     * @return ?\DateTimeInterface
     */
    public function getPublishUp()
    {
        return $this->publishUp;
    }

    /**
     * @param ?\DateTime $publishUp
     *
     * @return Message
     */
    public function setPublishUp($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);
        $this->publishUp = $publishUp;

        return $this;
    }

    /**
     * @return ?\DateTimeInterface
     */
    public function getPublishDown()
    {
        return $this->publishDown;
    }

    /**
     * @param ?\DateTime $publishDown
     *
     * @return Message
     */
    public function setPublishDown($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);
        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * @return ?Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param ?Category $category
     *
     * @return Message
     */
    public function setCategory($category)
    {
        $this->isChanged('category', $category);
        $this->category = $category;

        return $this;
    }

    /**
     * @return ArrayCollection<int,Channel>
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * @param ArrayCollection<int,Channel> $channels
     *
     * @return Message
     */
    public function setChannels($channels)
    {
        $this->isChanged('channels', $channels);
        $this->channels = $channels;

        return $this;
    }

    public function addChannel(Channel $channel): void
    {
        if (!$this->channels->contains($channel)) {
            $channel->setMessage($this);
            $this->isChanged('channels', $channel);

            $this->channels[$channel->getChannel()] = $channel;
        }
    }

    public function removeChannel(Channel $channel): void
    {
        if ($channel->getId()) {
            $this->isChanged('channels', $channel->getId());
        }
        $this->channels->removeElement($channel);
    }
}
