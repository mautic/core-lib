<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Controller\Api;

use DateTime;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use PHPUnit\Framework\Assert;
use Swift_Mime_SimpleMessage as SimpleMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SMimeFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $this->configParams['mailer_spool_type']       = 'testSendingSegmentEmailInMemoryWithSMime' === $this->getName() ? 'memory' : 'file';
        $this->configParams['smime_signing_enabled']   = true;
        $this->configParams['smime_certificates_path'] = '%kernel.project_dir%/app/bundles/EmailBundle/Tests/Mocks/Certificates/SMime';
        $this->configParams['mailer_from_email']       = 'admin@test-beta.mautibot.com';

        parent::setUp();
    }

    public function testSendingSegmentEmailInMemoryWithSMime(): void
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

        $response = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        Assert::assertSame('{"success":1,"percent":100,"progress":[2,2],"stats":{"sent":2,"failed":0,"failedRecipients":[]}}', $response->getContent());

        // Sort messages by to address as the order can differ
        $messages = $this->messageLogger->getMessages();
        Assert::assertTrue(usort(
            $messages,
            fn (SimpleMessage $a, SimpleMessage $b) => strcmp(array_key_first($a->getTo()), array_key_first($b->getTo()))
        ));

        // Warning, each email is logged twice in the messageLogger.
        Assert::assertStringContainsString('Hey anna@doe.email', $messages[0]->toString());
        Assert::assertStringContainsString('Hey john@doe.email', $messages[2]->toString());

        foreach ($messages as $message) {
            $this->assertMessageIsSigned($message);
        }
    }

    public function testSendingSegmentEmailInSpoolWithSMime(): void
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

        $response = $this->client->getResponse();
        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        Assert::assertSame('{"success":1,"percent":100,"progress":[2,2],"stats":{"sent":2,"failed":0,"failedRecipients":[]}}', $response->getContent());

        $this->runCommand('mautic:broadcasts:send');

        // Sort messages by to address as the order can differ
        $messages = $this->messageLogger->getMessages();
        Assert::assertTrue(usort(
            $messages,
            fn (SimpleMessage $a, SimpleMessage $b) => strcmp(array_key_first($a->getTo()), array_key_first($b->getTo()))
        ));

        Assert::assertStringContainsString('Hey anna@doe.email', $messages[0]->toString());
        Assert::assertStringContainsString('Hey john@doe.email', $messages[1]->toString());

        foreach ($messages as $message) {
            $this->assertMessageIsSigned($message);
        }
    }

    private function createSegment(string $name, string $alias): LeadList
    {
        $segment = new LeadList();
        $segment->setName($name);
        $segment->setAlias($alias);
        $this->em->persist($segment);

        return $segment;
    }

    private function createSegmentMember(Lead $contact, LeadList $segment): ListLead
    {
        $member = new ListLead();
        $member->setLead($contact);
        $member->setList($segment);
        $member->setDateAdded(new DateTime());
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
     * @param array<integer, mixed> $segments
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
        $email->setPublishUp(new DateTime('1 second ago'));
        $this->em->persist($email);

        return $email;
    }

    private function assertMessageIsSigned(SimpleMessage $message): void
    {
        $email = $message->toString();
        Assert::assertStringContainsString('Subject: Email A Subject', $email);
        Assert::assertStringContainsString('Content-Type: multipart/signed; protocol="application/x-pkcs7-signature";', $email);
        Assert::assertSame(1, substr_count($email, 'Content-Disposition: attachment; filename="smime.p7s"'), $email);
        Assert::assertSame(1, substr_count($email, 'Content-Type: application/x-pkcs7-signature; name="smime.p7s'), $email);
    }
}
