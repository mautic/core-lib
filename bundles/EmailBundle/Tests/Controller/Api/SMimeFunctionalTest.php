<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Controller\Api;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\RawMessage;

final class SMimeFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $this->configParams['smime_signing_enabled']   = true;
        $this->configParams['smime_certificates_path'] = '%kernel.project_dir%/app/bundles/EmailBundle/Tests/Mocks/Certificates/SMime';
        $this->configParams['mailer_from_email']       = 'admin@test-beta.mautibot.com';
        $this->configParams['messenger_dsn_email']     = 'sync://';
        $this->configParams['mailer_dsn']              = 'null://null';

        parent::setUp();
    }

    public function testSendingSegmentEmailWithSMime(): void
    {
        $segment  = $this->createSegment('Segment A', 'segment-a');
        $contact1 = $this->createContact('john@doe.email');
        $contact2 = $this->createContact('anna@doe.email');
        $this->createSegmentMember($contact1, $segment);
        $this->createSegmentMember($contact2, $segment);
        $this->em->flush();

        $email = $this->createEmail(
            'Email A',
            'Email A Subject',
            'list',
            'blank',
            '<h1>Hey {contactfield=email}</h1>',
            [$segment->getId() => $segment]
        );

        $this->em->flush();

        $this->client->request(
            Request::METHOD_POST,
            '/s/ajax?action=email:sendBatch',
            ['id' => $email->getId(), 'pending' => 2],
            [],
            $this->createAjaxHeaders()
        );

        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        // Assert that 2 emails were sent successfully
        Assert::assertEquals(1, $response['success']);
        Assert::assertEquals(100, $response['percent']);
        Assert::assertEquals([2, 2], $response['progress']);
        Assert::assertEquals(2, $response['stats']['sent']);
        Assert::assertEquals(0, $response['stats']['failed']);
        Assert::assertEmpty($response['stats']['failedRecipients']);

        // With sync messenger, emails are sent immediately
        $this->assertEmailCount(2);

        $message1 = $this->getMailerMessagesByToAddress('john@doe.email')[0];
        $message2 = $this->getMailerMessagesByToAddress('anna@doe.email')[0];

        Assert::assertStringContainsString('Hey john@doe.email', $message1->toString());
        Assert::assertStringContainsString('Hey anna@doe.email', $message2->toString());

        $this->assertMessageIsSigned($message1);
        $this->assertMessageIsSigned($message2);
    }

    private function createSegment(string $name, string $alias): LeadList
    {
        $segment = new LeadList();
        $segment->setName($name);
        $segment->setPublicName($name);
        $segment->setAlias($alias);
        $this->em->persist($segment);

        return $segment;
    }

    private function createSegmentMember(Lead $contact, LeadList $segment): ListLead
    {
        $member = new ListLead();
        $member->setLead($contact);
        $member->setList($segment);
        $member->setDateAdded(new \DateTime());
        $this->em->persist($member);

        return $member;
    }

    private function createContact(string $email): Lead
    {
        $contact = new Lead();
        $contact->setEmail($email);
        $this->em->persist($contact);

        return $contact;
    }

    /**
     * @param array<int, mixed> $segments
     *
     * @throws \Doctrine\ORM\ORMException
     */
    private function createEmail(string $name, string $subject, string $emailType, string $template, string $customHtml, array $segments = []): Email
    {
        $email = new Email();
        $email->setName($name);
        $email->setSubject($subject);
        $email->setEmailType($emailType);
        $email->setTemplate($template);
        $email->setCustomHtml($customHtml);
        $email->setLists($segments);
        $email->setPublishUp(new \DateTime('1 second ago'));
        $email->setIsPublished(true);
        $this->em->persist($email);

        return $email;
    }

    private function assertMessageIsSigned(RawMessage $message): void
    {
        $email = $message->toString();
        Assert::assertStringContainsString('Subject: Email A Subject', $email);
        Assert::assertStringContainsString('Content-Type: multipart/signed; protocol="application/x-pkcs7-signature";', $email);
        Assert::assertSame(1, substr_count($email, 'Content-Disposition: attachment; filename="smime.p7s"'), $email);
        Assert::assertSame(1, substr_count($email, 'Content-Type: application/x-pkcs7-signature; name="smime.p7s'), $email);
    }
}
