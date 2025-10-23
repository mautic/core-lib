<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Controller\Api;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
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
        // $this->configParams['messenger_dsn_email']     = 'in-memory://default';
        $this->configParams['messenger_dsn_email']     = 'sync://';
        $this->configParams['mailer_dsn']              = 'smtp://null:25';

        parent::setUp();
    }

    public function testSendingSegmentEmailWithSMime(): void
    {
        var_dump($this->configParams);
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
        Assert::assertJsonStringEqualsJsonString(
            '{"success":1,"percent":100,"progress":[2,2],"stats":{"sent":2,"failed":0,"failedRecipients":[]}}',
            $this->client->getResponse()->getContent()
        );

        // Get messages using Symfony Mailer's test assertions
        $messages = self::getMailerMessages();

        // Sort messages by to address as the order can differ
        // For signed messages, extract the to address from the raw content
        usort(
            $messages,
            function ($a, $b) {
                $toA = $this->extractToAddress($a);
                $toB = $this->extractToAddress($b);

                return strcmp($toA, $toB);
            }
        );

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
        $this->em->persist($email);

        return $email;
    }

    private function extractToAddress(RawMessage|MauticMessage $message): string
    {
        // For MauticMessage or Email, use getTo()
        if (method_exists($message, 'getTo')) {
            $to = $message->getTo();
            if ($to && isset($to[0])) {
                return $to[0]->getAddress();
            }
        }

        // For signed messages, extract from raw content
        $raw = $message->toString();
        if (preg_match('/To: (?:<)?([^>\r\n]+)(?:>)?/', $raw, $matches)) {
            return trim($matches[1]);
        }

        return '';
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
