<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Controller;

use Mautic\UserBundle\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;

final class AuditLogControllerTest extends MauticMysqlTestCase
{
    use CreateTestEntitiesTrait;
    private const SALES_USER = 'sales';

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testBatchExportActionAsAdmin(): void
    {
        $contact = $this->createLead('TestFirstName');
        $this->em->persist($contact);
        $this->em->flush();

        $this->client->request('GET', '/s/contacts/auditlog/batchExport/'.$contact->getId());
        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBatchExportActionAsUserNotPermission(): void
    {
        $contact = $this->createLead('TestFirstName');
        $this->em->persist($contact);
        $this->em->flush();

        $user = $this->em->getRepository(User::class)->findOneBy(['username' => self::SALES_USER]);
        $this->client->loginUser($user);
        $this->client->setServerParameter('PHP_AUTH_USER', self::SALES_USER);
        $this->client->request('GET', '/s/contacts/auditlog/batchExport/'.$contact->getId());

        $response = $this->client->getResponse();
        $this->assertEquals(403, $response->getStatusCode());
    }
}
