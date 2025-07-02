<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Tests\Controller;

use Mautic\CampaignBundle\Tests\Functional\Fixtures\FixtureHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Tests\Functional\Fixtures\EmailFixturesHelper;
use Mautic\LeadBundle\Entity\Lead;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

class CampaignMetricsControllerFunctionalTest extends MauticMysqlTestCase
{
    private FixtureHelper $campaignFixturesHelper;
    private EmailFixturesHelper $emailFixturesHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaignFixturesHelper = new FixtureHelper($this->em);
        $this->emailFixturesHelper    = new EmailFixturesHelper($this->em);
    }

    /**
     * @return array<string, mixed>
     */
    private function setupEmailCampaignTestData(): array
    {
        $contacts = [
            $this->campaignFixturesHelper->createContact('john@example.com'),
            $this->campaignFixturesHelper->createContact('paul@example.com'),
        ];

        $email = $this->emailFixturesHelper->createEmail('Test Email');
        $this->em->flush();

        $campaign      = $this->campaignFixturesHelper->createCampaignWithEmailSent($email->getId());
        $this->campaignFixturesHelper->addContactToCampaign($contacts[0], $campaign);
        $this->campaignFixturesHelper->addContactToCampaign($contacts[1], $campaign);
        $eventId = $campaign->getEmailSendEvents()->first()->getId();

        $emailStats = [
            $this->emailFixturesHelper->emulateEmailSend($contacts[0], $email, '2024-12-10 12:00:00', 'campaign.event', $eventId),
            $this->emailFixturesHelper->emulateEmailSend($contacts[1], $email, '2024-12-10 12:00:00', 'campaign.event', $eventId),
        ];

        $this->emailFixturesHelper->emulateEmailRead($emailStats[0], $email, '2024-12-10 12:09:00');
        $this->emailFixturesHelper->emulateEmailRead($emailStats[1], $email, '2024-12-11 21:35:00');

        $this->em->flush();
        $this->em->persist($email);

        $emailLinks = [
            $this->emailFixturesHelper->createEmailLink('https://example.com/1', $email->getId()),
            $this->emailFixturesHelper->createEmailLink('https://example.com/2', $email->getId()),
        ];
        $this->em->flush();

        $this->emailFixturesHelper->emulateLinkClick($email, $emailLinks[0], $contacts[0], '2024-12-10 12:10:00', 3);
        $this->emailFixturesHelper->emulateLinkClick($email, $emailLinks[1], $contacts[0], '2024-12-10 13:20:00');
        $this->emailFixturesHelper->emulateLinkClick($email, $emailLinks[1], $contacts[1], '2024-12-11 21:37:00');
        $this->em->flush();

        return ['campaign' => $campaign, 'email' => $email];
    }

    public function testEmailWeekdaysAction(): void
    {
        $testData = $this->setupEmailCampaignTestData();
        $campaign = $testData['campaign'];

        $this->client->request(Request::METHOD_GET, "/s/campaign/metrics/email-weekdays/{$campaign->getId()}/2024-12-01/2024-12-12");
        Assert::assertTrue($this->client->getResponse()->isOk());
        $content      = $this->client->getResponse()->getContent();
        $crawler      = new Crawler($content);
        $daysJson     = $crawler->filter('canvas')->text(null, false);
        $daysData     = json_decode(html_entity_decode($daysJson), true);
        $daysDatasets = $daysData['datasets'];
        Assert::assertIsArray($daysDatasets);
        Assert::assertCount(3, $daysDatasets);  // Assuming there are 3 datasets: Email sent, Email read, Email clicked

        $expectedDaysLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $expectedDaysData   = [
            ['label' => 'Email sent', 'data' => [0, 2, 0, 0, 0, 0, 0]],
            ['label' => 'Email read', 'data' => [0, 1, 1, 0, 0, 0, 0]],
            ['label' => 'Email clicked', 'data' => [0, 4, 1, 0, 0, 0, 0]],
        ];
        Assert::assertEquals($expectedDaysLabels, $daysData['labels']);
        foreach ($daysDatasets as $index => $dataset) {
            Assert::assertEquals($expectedDaysData[$index]['label'], $dataset['label']);
            Assert::assertEquals($expectedDaysData[$index]['data'], $dataset['data']);
        }
    }

    public function testEmailHoursAction(): void
    {
        $testData = $this->setupEmailCampaignTestData();
        $campaign = $testData['campaign'];

        $this->client->request(Request::METHOD_GET, "/s/campaign/metrics/email-hours/{$campaign->getId()}/2024-12-01/2024-12-12");
        Assert::assertTrue($this->client->getResponse()->isOk());
        $content   = $this->client->getResponse()->getContent();
        $crawler   = new Crawler($content);
        $hourJson  = $crawler->filter('canvas')->text(null, false);
        $hoursData = json_decode(html_entity_decode($hourJson), true);

        $hoursDatasets = $hoursData['datasets'];
        Assert::assertIsArray($hoursDatasets);
        Assert::assertCount(3, $hoursDatasets);  // Assuming there are 3 datasets: Email sent, Email read, Email clicked

        // Get the time format from CoreParametersHelper
        $coreParametersHelper = self::getContainer()->get('mautic.helper.core_parameters');
        $timeFormat           = $coreParametersHelper->get('date_format_timeonly');

        // Generate expected hour labels based on the actual time format
        $expectedHoursLabels = [];
        for ($hour = 0; $hour < 24; ++$hour) {
            $startTime             = (new \DateTime())->setTime($hour, 0);
            $endTime               = (new \DateTime())->setTime(($hour + 1) % 24, 0);
            $expectedHoursLabels[] = $startTime->format($timeFormat).' - '.$endTime->format($timeFormat);
        }

        Assert::assertEquals($expectedHoursLabels, $hoursData['labels']);

        $expectedHoursData = [
            ['label' => 'Email sent', 'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
            ['label' => 'Email read', 'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0]],
            ['label' => 'Email clicked', 'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0]],
        ];
        foreach ($hoursDatasets as $index => $dataset) {
            Assert::assertEquals($expectedHoursData[$index]['label'], $dataset['label']);
            Assert::assertEquals($expectedHoursData[$index]['data'], $dataset['data']);
        }
    }

    public function testEventDetailsAction(): void
    {
        /** @var array<int, Lead> $contacts */
        $contacts = [
            $this->campaignFixturesHelper->createContact('john@example.com'),
            $this->campaignFixturesHelper->createContact('paul@example.com'),
            $this->campaignFixturesHelper->createContact('mawka@example.com'),
            $this->campaignFixturesHelper->createContact('heksa@example.com'),
        ];
        $contacts[0]->setPoints(1);
        $contacts[2]->setPoints(1);
        $contacts[3]->setPoints(1);
        $this->em->persist($contacts[0]);
        $this->em->persist($contacts[2]);
        $this->em->persist($contacts[3]);
        $this->em->flush();

        $email = $this->emailFixturesHelper->createEmail('Test Email');
        $this->em->flush();
        $emailId = $email->getId();

        $emailLinks = [
            $this->emailFixturesHelper->createEmailLink('https://example.com/1', $emailId),
            $this->emailFixturesHelper->createEmailLink('https://example.com/2', $emailId),
        ];
        $this->em->flush();

        $campaign      = $this->campaignFixturesHelper->createCampaignWithConditionalEmail($emailId);
        foreach ($contacts as $contact) {
            $this->campaignFixturesHelper->addContactToCampaign($contact, $campaign);
        }
        $this->em->flush();
        $this->em->clear();

        $events         = $campaign->getEvents();
        $conditionEvent = $events->first();
        $emailEvent     = $events->last();

        // check condition event details before running the campaign
        $this->client->request(Request::METHOD_GET, "/s/campaign/metrics/event-details/{$conditionEvent->getId()}");
        $clientResponse = $this->client->getResponse();
        $this->assertResponseIsSuccessful($clientResponse->getContent());
        $conditionEventDetails = json_decode($clientResponse->getContent(), true);
        $this->assertEquals([
            'total_executions'     => ['value' => 0, 'tooltip' => null],
            'pending_executions'   => ['value' => 0, 'tooltip' => null],
            'negative_path_count'  => ['value' => 0, 'tooltip' => null],
            'positive_path_count'  => ['value' => 0, 'tooltip' => null],
        ], $conditionEventDetails);

        // check email event details before running the campaign
        $this->client->request(Request::METHOD_GET, "/s/campaign/metrics/event-details/{$emailEvent->getId()}");
        $clientResponse = $this->client->getResponse();
        $this->assertResponseIsSuccessful($clientResponse->getContent());
        $emailEventDetails = json_decode($clientResponse->getContent(), true);
        $this->assertEquals([
            'total_executions'          => ['value' => 0, 'tooltip' => null],
            'pending_executions'        => ['value' => 0, 'tooltip' => null],
            'sent_count'                => ['value' => 0, 'tooltip' => null],
            'read_count'                => ['value' => 0, 'tooltip' => null],
            'clicked_count'             => ['value' => 0, 'tooltip' => null],
            'open_rate'                 => ['value' => '0%', 'tooltip' => null],
            'click_through_rate'        => ['value' => '0%', 'tooltip' => null],
            'click_through_open_rate'   => ['value' => '0%', 'tooltip' => null],
        ], $emailEventDetails);

        $commandResult = $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaign->getId()]);
        Assert::assertStringContainsString('7 total events were executed', $commandResult->getDisplay());

        // check condition event details after running the campaign
        $this->client->request(Request::METHOD_GET, "/s/campaign/metrics/event-details/{$conditionEvent->getId()}");
        $clientResponse = $this->client->getResponse();
        $this->assertResponseIsSuccessful($clientResponse->getContent());
        $conditionEventDetails = json_decode($clientResponse->getContent(), true);
        $this->assertNotEmpty($conditionEventDetails['first_execution_date']['value'], 'First execution date should not be empty');
        $this->assertNotEmpty($conditionEventDetails['first_execution_date']['tooltip'], 'First execution date should not be empty');
        $this->assertNotEmpty($conditionEventDetails['last_execution_date']['value'], 'Last execution date should not be empty');
        $this->assertNotEmpty($conditionEventDetails['last_execution_date']['tooltip'], 'Last execution date should not be empty');
        $this->assertEquals(4, $conditionEventDetails['total_executions']['value']);
        $this->assertEquals(0, $conditionEventDetails['pending_executions']['value']);
        $this->assertEquals(1, $conditionEventDetails['negative_path_count']['value']);
        $this->assertEquals(3, $conditionEventDetails['positive_path_count']['value']);

        // check email event details after running the campaign
        $this->client->request(Request::METHOD_GET, "/s/campaign/metrics/event-details/{$emailEvent->getId()}");
        $clientResponse = $this->client->getResponse();
        $this->assertResponseIsSuccessful($clientResponse->getContent());
        $emailEventDetails = json_decode($clientResponse->getContent(), true);
        $this->assertNotEmpty($emailEventDetails['first_execution_date']['value'], 'First execution date should not be empty');
        $this->assertNotEmpty($emailEventDetails['first_execution_date']['tooltip'], 'First execution date should not be empty');
        $this->assertNotEmpty($emailEventDetails['last_execution_date']['value'], 'Last execution date should not be empty');
        $this->assertNotEmpty($emailEventDetails['last_execution_date']['tooltip'], 'Last execution date should not be empty');
        $this->assertEquals(3, $emailEventDetails['total_executions']['value']);
        $this->assertEquals(0, $emailEventDetails['pending_executions']['value']);
        $this->assertEquals(3, $emailEventDetails['sent_count']['value']);
        $this->assertEquals(0, $emailEventDetails['read_count']['value']);
        $this->assertEquals(0, $emailEventDetails['clicked_count']['value']);
        $this->assertEquals('0%', $emailEventDetails['open_rate']['value']);
        $this->assertEquals('0%', $emailEventDetails['click_through_rate']['value']);
        $this->assertEquals('0%', $emailEventDetails['click_through_open_rate']['value']);

        // emulate email read and link click
        $emailStats = $this->em->getRepository(Stat::class)->findBy(['email' => $email]);
        $email      = $emailStats[0]->getEmail();
        Assert::assertCount(3, $emailStats);
        $this->emailFixturesHelper->emulateEmailRead($emailStats[0], $email);
        $this->emailFixturesHelper->emulateEmailRead($emailStats[1], $email);
        $this->em->flush();

        $this->emailFixturesHelper->emulateLinkClick($email, $emailLinks[0], $emailStats[0]->getLead());
        $this->em->flush();

        // check email event details after emulating read and click
        $this->client->request(Request::METHOD_GET, "/s/campaign/metrics/event-details/{$emailEvent->getId()}");
        $clientResponse = $this->client->getResponse();
        $this->assertResponseIsSuccessful($clientResponse->getContent());
        $emailEventDetails = json_decode($clientResponse->getContent(), true);
        $this->assertNotEmpty($emailEventDetails['first_execution_date']['value'], 'First execution date should not be empty');
        $this->assertNotEmpty($emailEventDetails['first_execution_date']['tooltip'], 'First execution date should not be empty');
        $this->assertNotEmpty($emailEventDetails['last_execution_date']['value'], 'Last execution date should not be empty');
        $this->assertNotEmpty($emailEventDetails['last_execution_date']['tooltip'], 'Last execution date should not be empty');
        $this->assertEquals(3, $emailEventDetails['total_executions']['value']);
        $this->assertEquals(0, $emailEventDetails['pending_executions']['value']);
        $this->assertEquals(3, $emailEventDetails['sent_count']['value']);
        $this->assertEquals(2, $emailEventDetails['read_count']['value']);
        $this->assertEquals(1, $emailEventDetails['clicked_count']['value']);
        $this->assertEquals('66.67%', $emailEventDetails['open_rate']['value']);
        $this->assertEquals('33.33%', $emailEventDetails['click_through_rate']['value']);
        $this->assertEquals('50%', $emailEventDetails['click_through_open_rate']['value']);
    }
}
