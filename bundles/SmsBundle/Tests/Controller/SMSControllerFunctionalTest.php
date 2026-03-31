<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\ProjectBundle\Entity\Project;
use Mautic\SmsBundle\Entity\Sms;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

final class SMSControllerFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $this->configParams['site_url'] = 'https://localhost';
        parent::setUp();
    }

    public function testSmsWithProject(): void
    {
        $sms = $this->CreateSms();

        $project = new Project();
        $project->setName('Test Project');
        $this->em->persist($project);

        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/s/sms/edit/'.$sms->getId());
        $form    = $crawler->selectButton('Save')->form();
        $form['sms[projects]']->setValue((string) $project->getId());

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();

        $savedSms = $this->em->find(Sms::class, $sms->getId());
        Assert::assertSame($project->getId(), $savedSms->getProjects()->first()->getId());
    }

    public function testListPageMMSIndicator(): void
    {
        $mediaSmsName = 'Media attached sms';
        $this->CreateSms($mediaSmsName, 'sms body', true, range(1, 3));
        $this->CreateSms('sms', 'sms body2');
        $crawler  = $this->client->request(Request::METHOD_GET, '/s/sms');
        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $crawler->filter('.sms-list tbody tr'));
        $this->assertCount(1, $crawler->filter('.sms-list tbody i.fa-file-image-o'));
        $this->assertStringContainsString($mediaSmsName, $crawler->filter('.sms-list tbody i.fa-file-image-o')->closest('tr')->text());
    }

    public function testPreviewMMS(): void
    {
        $this->createAndVerifyMedia('preview');
    }

    public function testDetailPage(): void
    {
        $this->createAndVerifyMedia('view');
    }

    public function testSmsWithMediaLimitError(): void
    {
        $sms            = $this->CreateSms('sms', 'sms body', true, range(1, 11));
        $crawler        = $this->client->request(Request::METHOD_GET, '/s/sms/edit/'.$sms->getId());
        $buttonCrawler  =  $crawler->selectButton('Save & Close');
        $form           = $buttonCrawler->form();

        $form->setValues([
            'sms[name]'    => 'sms',
            'sms[message]' => 'sms body',
            'sms[isMms]'   => 1,
            'sms[media]'   => range(1, 11),
        ]);

        $crawler  = $this->client->submit($form);
        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());
        Assert::assertStringContainsString('Maximum 10 media could be attached with 1 MMS', $crawler->filter('#media_div .has-error')->text());
    }

    public function testCloneSmsWithMediaSuccessfully(): void
    {
        $clonedName    = 'sms clone';
        $clonedMessage = 'sms body clone';
        $media         = ['a.png', 'b.jpg'];

        $sms = $this->CreateSms('sms', 'sms body', true, $media);

        $crawler = $this->client->request(Request::METHOD_GET, '/s/sms/clone/'.$sms->getId());

        $buttonCrawler  =  $crawler->selectButton('Save & Close');
        $form           = $buttonCrawler->form();
        $form->setValues([
            'sms[name]'    => 'sms clone',
            'sms[message]' => 'sms body clone',
        ]);

        $this->client->submit($form);
        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());
        $savedSms = $this->em->getRepository(Sms::class)->findOneBy(['name' => $clonedName]);
        Assert::assertInstanceOf(Sms::class, $savedSms);
        Assert::assertSame($clonedMessage, $savedSms->getMessage());
        Assert::assertSame($media, $savedSms->getMedia());
        Assert::assertTrue($savedSms->getIsMms());
    }

    public function testSaveSmsWithMediaFalse(): void
    {
        $media   = ['a.png', 'b.jpg'];
        $sms     = $this->CreateSms('sms', 'sms body', true, $media);
        $crawler = $this->client->request(Request::METHOD_GET, '/s/sms/edit/'.$sms->getId());

        $buttonCrawler  =  $crawler->selectButton('Save & Close');
        $form           = $buttonCrawler->form();
        $form->setValues([
            'sms[isMms]'    => 0,
        ]);

        $this->client->submit($form);
        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());
        $savedSms = $this->em->getRepository(Sms::class)->find($sms->getId());
        Assert::assertInstanceOf(Sms::class, $savedSms);
        Assert::assertSame([], $savedSms->getMedia());
        Assert::assertFalse($savedSms->getIsMms());
    }

    /**
     * @param array<mixed> $media
     */
    private function CreateSms(string $name = 'sms', string $message = 'sms body', bool $isMms = false, ?array $media = null): Sms
    {
        $sms = new Sms();
        $sms->setName($name);
        $sms->setMessage($message);
        if (!empty($media)) {
            $sms->setMedia($media);
        }
        $sms->setIsMms($isMms);
        $sms->setSmsType('template');
        $this->em->persist($sms);
        $this->em->flush();

        return $sms;
    }

    private function createAndVerifyMedia(string $page): void
    {
        $media    = range(1, 3);
        $sms      = $this->CreateSms('Media attached sms', 'sms body', true, $media);
        $crawler  = $this->client->request(Request::METHOD_GET, '/s/sms/'.$page.'/'.$sms->getId());
        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $mediaDivs = $crawler->filter('div.phone-preview__message--media');
        $this->assertCount(3, $mediaDivs);
        foreach ($media as $key => $value) {
            $img = $mediaDivs->eq($key)->filter('img');
            $this->assertSame((string) $value, $img->attr('src'));
        }
    }
}
