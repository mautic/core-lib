<?php

declare(strict_types=1);

namespace Mautic\NotificationBundle\Tests\Functional\Helper;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\NotificationBundle\Helper\NotificationHelper;

final class NotificationHelperTest extends MauticMysqlTestCase
{
    private NotificationHelper $notificationHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationHelper = static::getContainer()->get(NotificationHelper::class);
    }

    public function testUnsubscribeForExistingContactCreatesDncEntry(): void
    {
        $email = sprintf('notification-helper-%s@example.com', bin2hex(random_bytes(8)));

        $lead = new Lead();
        $lead->setEmail($email);
        $this->em->persist($lead);
        $this->em->flush();

        $result = $this->notificationHelper->unsubscribe($email);

        $this->assertNotFalse($result);

        $this->em->clear();

        $lead = $this->em->getRepository(Lead::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($lead);

        $entries = $this->em->getRepository(DoNotContact::class)->findBy([
            'lead'    => $lead,
            'channel' => 'notification',
            'reason'  => DoNotContact::UNSUBSCRIBED,
        ]);

        $this->assertCount(1, $entries);
    }

    public function testUnsubscribeForUnknownEmailReturnsFalse(): void
    {
        $email = sprintf('notification-helper-%s@example.com', bin2hex(random_bytes(8)));

        $result = $this->notificationHelper->unsubscribe($email);

        $this->assertFalse($result);
    }
}
