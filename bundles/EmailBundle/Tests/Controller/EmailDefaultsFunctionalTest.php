<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\PageBundle\Entity\Page;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

/**
 * Functional tests for email form pre-population from global config defaults
 * (default preference center and UTM tag defaults).
 *
 * These tests run with the config params set so they do not bleed into the
 * unrelated tests in EmailFunctionalTest.
 */
class EmailDefaultsFunctionalTest extends MauticMysqlTestCase
{
    public const SAVE_AND_CLOSE = 'Save & Close';

    protected function setUp(): void
    {
        $this->configParams['email_default_preference_center_id'] = 1;
        $this->configParams['email_default_utm_source']           = 'config-source';
        $this->configParams['email_default_utm_medium']           = 'config-medium';
        $this->configParams['email_default_utm_campaign']         = 'config-campaign';
        $this->configParams['email_default_utm_content']          = 'config-content';

        parent::setUp();
    }

    public function testNewEmailFormPreselectsConfiguredPreferenceCenterAndUtmDefaults(): void
    {
        $this->resetAutoincrement(['pages']);
        $preferenceCenter = $this->createPreferenceCenterPage('Default Preference Center');
        $this->em->flush();

        Assert::assertSame(1, $preferenceCenter->getId());

        $crawler = $this->client->request(Request::METHOD_GET, '/s/emails/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SAVE_AND_CLOSE)->form();

        Assert::assertSame((string) $preferenceCenter->getId(), $form['emailform[preferenceCenter]']->getValue());
        Assert::assertSame('config-source', $form['emailform[utmTags][utmSource]']->getValue());
        Assert::assertSame('config-medium', $form['emailform[utmTags][utmMedium]']->getValue());
        Assert::assertSame('config-campaign', $form['emailform[utmTags][utmCampaign]']->getValue());
        Assert::assertSame('config-content', $form['emailform[utmTags][utmContent]']->getValue());
    }

    public function testEditFormDoesNotOverwriteExistingPreferenceCenterAndUtmValues(): void
    {
        $this->resetAutoincrement(['pages']);
        $configuredPage = $this->createPreferenceCenterPage('Configured Preference Center');
        $existingPage   = $this->createPreferenceCenterPage('Existing Preference Center');

        $email = $this->createEmail();
        $email->setPreferenceCenter($existingPage);
        $email->setUtmTags([
            'utmSource'   => 'existing-source',
            'utmMedium'   => 'existing-medium',
            'utmCampaign' => 'existing-campaign',
            'utmContent'  => 'existing-content',
        ]);

        $this->em->flush();
        $this->em->clear();

        Assert::assertSame(1, $configuredPage->getId());

        $crawler = $this->client->request(Request::METHOD_GET, "/s/emails/edit/{$email->getId()}");
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SAVE_AND_CLOSE)->form();

        Assert::assertSame((string) $existingPage->getId(), $form['emailform[preferenceCenter]']->getValue());
        Assert::assertSame('existing-source', $form['emailform[utmTags][utmSource]']->getValue());
        Assert::assertSame('existing-medium', $form['emailform[utmTags][utmMedium]']->getValue());
        Assert::assertSame('existing-campaign', $form['emailform[utmTags][utmCampaign]']->getValue());
        Assert::assertSame('existing-content', $form['emailform[utmTags][utmContent]']->getValue());
    }

    public function testCloneFormKeepsExplicitCloneValuesInsteadOfConfigDefaults(): void
    {
        $this->resetAutoincrement(['pages']);
        $configuredPage = $this->createPreferenceCenterPage('Configured Preference Center');
        $clonedPage     = $this->createPreferenceCenterPage('Cloned Preference Center');

        $email = $this->createEmail();
        $email->setPreferenceCenter($clonedPage);
        $email->setUtmTags([
            'utmSource'   => 'clone-source',
            'utmMedium'   => 'clone-medium',
            'utmCampaign' => 'clone-campaign',
            'utmContent'  => 'clone-content',
        ]);

        $this->em->flush();

        Assert::assertSame(1, $configuredPage->getId());

        $crawler = $this->client->request(Request::METHOD_GET, "/s/emails/clone/{$email->getId()}");
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SAVE_AND_CLOSE)->form();

        Assert::assertSame((string) $clonedPage->getId(), $form['emailform[preferenceCenter]']->getValue());
        Assert::assertSame('clone-source', $form['emailform[utmTags][utmSource]']->getValue());
        Assert::assertSame('clone-medium', $form['emailform[utmTags][utmMedium]']->getValue());
        Assert::assertSame('clone-campaign', $form['emailform[utmTags][utmCampaign]']->getValue());
        Assert::assertSame('clone-content', $form['emailform[utmTags][utmContent]']->getValue());
    }

    public function testCloneOfEmailWithBlankFieldsDoesNotInheritConfigDefaults(): void
    {
        // Source email has no preference center and no UTM tags set — intentionally blank.
        // The clone must preserve those blank values rather than inheriting global defaults.
        $email = $this->createEmail();
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, "/s/emails/clone/{$email->getId()}");
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SAVE_AND_CLOSE)->form();

        Assert::assertSame('', $form['emailform[preferenceCenter]']->getValue());
        Assert::assertSame('', $form['emailform[utmTags][utmSource]']->getValue());
        Assert::assertSame('', $form['emailform[utmTags][utmMedium]']->getValue());
        Assert::assertSame('', $form['emailform[utmTags][utmCampaign]']->getValue());
        Assert::assertSame('', $form['emailform[utmTags][utmContent]']->getValue());
    }

    private function createEmail(): Email
    {
        $email = new Email();
        $email->setName('Email name');
        $email->setSubject('Email subject');
        $email->setEmailType('list');
        $email->setTemplate('some-template');
        $email->setCustomHtml('{}');
        $this->em->persist($email);

        return $email;
    }

    private function createPreferenceCenterPage(string $name): Page
    {
        $page = new Page();
        $page->setTitle($name);
        $page->setAlias(mb_strtolower(str_replace(' ', '-', $name)));
        $page->setIsPreferenceCenter(true);
        $page->setCustomHtml('<html><body>Preference Center Page</body></html>');
        $page->setIsPublished(true);
        $this->em->persist($page);

        return $page;
    }
}
