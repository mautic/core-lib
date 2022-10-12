<?php

declare(strict_types=1);

namespace Mautic\AllydeBundle\Tests\Controller;

use Mautic\AllydeBundle\Entity\BeeFreeEmail;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\EmailDraft;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class EmailDraftFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $this->configParams['email_draft_enabled'] = true;

        parent::setUp();
    }

    public function testSaveDraftAndApplyDraftForBee(): void
    {
        $email = $this->createNewEmail('beefree-empty');
        $this->createBeeFreeEmail($email);
        $this->applyDraft($email);
    }

    public function testDiscardDraftForBee(): void
    {
        $email = $this->createNewEmail('beefree-empty');
        $this->createBeeFreeEmail($email);
        $this->discardDraft($email);
    }

    private function applyDraft(Email $email): void
    {
        $this->saveDraft($email);
        $crawler = $this->client->request(Request::METHOD_GET, "/s/emails/edit/{$email->getId()}");
        $form    = $crawler->selectButton('Apply Draft')->form();
        $this->client->submit($form);
        Assert::assertTrue($this->client->getResponse()->isOk());

        $emailDraft = $this->em->getRepository(EmailDraft::class)->findOneBy(['email' => $email]);

        Assert::assertNull($emailDraft);
        Assert::assertSame('Test html Draft', $email->getCustomHtml());
    }

    private function discardDraft(Email $email): void
    {
        $this->saveDraft($email);
        $crawler = $this->client->request(Request::METHOD_GET, "/s/emails/edit/{$email->getId()}");
        $form    = $crawler->selectButton('Discard Draft')->form();
        $this->client->submit($form);
        Assert::assertTrue($this->client->getResponse()->isOk());

        $emailDraft = $this->em->getRepository(EmailDraft::class)->findOneBy(['email' => $email]);

        Assert::assertNull($emailDraft);
        Assert::assertSame('Test html', $email->getCustomHtml());
    }

    private function saveDraft(Email $email): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, "/s/emails/edit/{$email->getId()}");

        $form                          = $crawler->selectButton('Save as Draft')->form();
        $beefreeContent                = [
            'css'      => 'the css',
            'html'     => 'the html',
            'rendered' => 'Test html Draft',
        ];

        $form['emailform[customHtml]']->setValue(json_encode($beefreeContent));
        $this->client->submit($form);
        Assert::assertTrue($this->client->getResponse()->isOk());

        $emailDraft = $this->em->getRepository(EmailDraft::class)->findOneBy(['email' => $email]);
        Assert::assertEquals('Test html Draft', $emailDraft->getHtml());
        Assert::assertSame('Test html', $email->getCustomHtml());
    }

    private function createNewEmail(string $templateName = 'blank', string $templateContent = 'Test html'): Email
    {
        $email = new Email();
        $email->setName('Email A');
        $email->setSubject('Email A Subject');
        $email->setEmailType('template');
        $email->setTemplate($templateName);
        $email->setCustomHtml($templateContent);
        $this->em->persist($email);
        $this->em->flush();

        return $email;
    }

    private function createBeeFreeEmail(Email $email, string $json = '{"content":{"page":"email"}}'): BeeFreeEmail
    {
        $beeFreeEmail = new BeeFreeEmail($email);
        $beeFreeEmail->setHtml($json);
        $beeFreeEmail->setCss('');
        $this->em->persist($beeFreeEmail);

        return $beeFreeEmail;
    }
}
