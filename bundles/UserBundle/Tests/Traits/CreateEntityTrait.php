<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Tests\Traits;

use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;

trait CreateEntityTrait
{
    public function createRole(bool $isAdmin = false): Role
    {
        $role = new Role();
        $role->setName('Role');
        $role->setIsAdmin($isAdmin);
        $this->em->persist($role);

        return $role;
    }

    public function createUser(Role $role, string $email = 'test@acquia.com', string $password = 'mautic'): User
    {
        $userName = explode('@', $email)[0].rand();
        $user     = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setUsername($userName);
        $user->setEmail($email);
        $encoder = $this->getContainer()->get('security.encoder_factory')->getEncoder($user);
        $user->setPassword($encoder->encodePassword($password, null));
        $user->setRole($role);
        $this->em->persist($user);

        return $user;
    }
}
