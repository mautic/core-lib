<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

class UserInvite
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $token;

    /**
     * @var \DateTimeInterface
     */
    private $expiration;

    /**
     * @var bool
     */
    private $used = false;

    /**
     * @var Role|null
     */
    private $role;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('user_invites');
        $builder->addId();

        $builder->createField('email', 'string')
            ->length(191)
            ->build();

        $builder->createField('token', 'string')
            ->length(120)
            ->unique()
            ->build();

        $builder->createField('expiration', 'datetime')
            ->build();

        $builder->createField('used', 'boolean')
            ->build();

        $builder->createManyToOne('role', Role::class)
            ->addJoinColumn('role_id', 'id', true, false, 'CASCADE')
            ->build();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getExpiration(): \DateTimeInterface
    {
        return $this->expiration;
    }

    public function setExpiration(\DateTimeInterface $expiration): self
    {
        $this->expiration = $expiration;

        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): self
    {
        $this->used = $used;

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;

        return $this;
    }
}
