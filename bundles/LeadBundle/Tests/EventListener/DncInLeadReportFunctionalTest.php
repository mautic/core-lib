<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\ReportBundle\Entity\Report;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

class DncInLeadReportFunctionalTest extends MauticMysqlTestCase
{
    public function testLeadReportWithDncListColumn(): void
    {
        $leads[] = $this->createContact('test1@example.com');
        $leads[] = $this->createContact('test2@example.com');
        $leads[] = $this->createContact('test3@example.com');
        $this->em->flush();

        $this->createDnc('email', $leads[0], DoNotContact::BOUNCED);
        $this->createDnc('email', $leads[1], DoNotContact::MANUAL);
        $this->createDnc('email', $leads[2], DoNotContact::UNSUBSCRIBED);
        $this->createDnc('sms', $leads[2], DoNotContact::MANUAL);
        $this->em->flush();

        $report = $this->createReport();

        $crawler            = $this->client->request(Request::METHOD_GET, "/s/reports/view/{$report->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());
        $crawlerReportTable = $crawler->filterXPath('//table[@id="reportTable"]')->first();

        // convert html table to php array
        $crawlerReportTable = array_slice($this->domTableToArray($crawlerReportTable), 1, 3);

        $this->assertSame([
            // no., id, dnc_list
            ['1', (string) $leads[0]->getId(), 'DNC Bounced: Email'],
            ['2', (string) $leads[1]->getId(), 'DNC Manually Unsubscribed: Email'],
            ['3', (string) $leads[2]->getId(), 'DNC Manually Unsubscribed: Text Message, DNC Unsubscribed: Email'],
        ], $crawlerReportTable);
    }

    public function testLeadReportWithDncListFilterIn(): void
    {
        $leads[] = $this->createContact('test1@example.com');
        $leads[] = $this->createContact('test2@example.com');
        $leads[] = $this->createContact('test3@example.com');
        $this->em->flush();

        $this->createDnc('email', $leads[0], DoNotContact::BOUNCED);
        $this->createDnc('email', $leads[2], DoNotContact::UNSUBSCRIBED);
        $this->createDnc('sms', $leads[2], DoNotContact::MANUAL);
        $this->em->flush();

        $report = $this->createReport();
        $report->setFilters([
            [
                'column'    => 'dnc',
                'glue'      => 'and',
                'dynamic'   => null,
                'condition' => 'in',
                'value'     => [
                    'email:'.DoNotContact::UNSUBSCRIBED,
                    'email:'.DoNotContact::BOUNCED,
                ],
            ],
        ]);
        $this->em->persist($report);
        $this->em->flush();

        $crawler            = $this->client->request(Request::METHOD_GET, "/s/reports/view/{$report->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());
        $crawlerReportTable = $crawler->filterXPath('//table[@id="reportTable"]')->first();

        // convert html table to php array
        $crawlerReportTable = array_slice($this->domTableToArray($crawlerReportTable), 1, 2);

        $this->assertSame([
            // no., id, dnc_list
            ['1', (string) $leads[0]->getId(), 'DNC Bounced: Email'],
            ['2', (string) $leads[2]->getId(), 'DNC Manually Unsubscribed: Text Message, DNC Unsubscribed: Email'],
        ], $crawlerReportTable);
    }

    public function testLeadReportWithDncListFilterNotIn(): void
    {
        $leads[] = $this->createContact('test1@example.com');
        $leads[] = $this->createContact('test2@example.com');
        $leads[] = $this->createContact('test3@example.com');
        $this->em->flush();

        $this->createDnc('email', $leads[0], DoNotContact::BOUNCED);
        $this->createDnc('email', $leads[1], DoNotContact::MANUAL);
        $this->createDnc('sms', $leads[2], DoNotContact::MANUAL);
        $this->em->flush();

        $report = $this->createReport();
        $report->setFilters([
            [
                'column'    => 'dnc',
                'glue'      => 'and',
                'dynamic'   => null,
                'condition' => 'notIn',
                'value'     => ['email:'.DoNotContact::BOUNCED], // Exclude bounced emails
            ],
        ]);
        $this->em->persist($report);
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, "/s/reports/view/{$report->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());
        $crawlerReportTable = $crawler->filterXPath('//table[@id="reportTable"]')->first();

        $crawlerReportTable = array_slice($this->domTableToArray($crawlerReportTable), 1, 2);

        $this->assertSame([
            ['1', (string) $leads[1]->getId(), 'DNC Manually Unsubscribed: Email'],
            ['2', (string) $leads[2]->getId(), 'DNC Manually Unsubscribed: Text Message'],
        ], $crawlerReportTable);
    }

    public function testLeadReportWithDncListFilterEmpty(): void
    {
        $leads[] = $this->createContact('test1@example.com');
        $leads[] = $this->createContact('test2@example.com');
        $leads[] = $this->createContact('test3@example.com');
        $this->em->flush();

        // Only add DNC for first two contacts
        $this->createDnc('email', $leads[0], DoNotContact::BOUNCED);
        $this->createDnc('email', $leads[1], DoNotContact::MANUAL);
        $this->em->flush();

        $report = $this->createReport();
        $report->setFilters([
            [
                'column'    => 'dnc',
                'glue'      => 'and',
                'dynamic'   => null,
                'condition' => 'empty',
                'value'     => [], // Empty value as we're looking for contacts without DNC
            ],
        ]);
        $this->em->persist($report);
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, "/s/reports/view/{$report->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());
        $crawlerReportTable = $crawler->filterXPath('//table[@id="reportTable"]')->first();

        $crawlerReportTable = array_slice($this->domTableToArray($crawlerReportTable), 1, 1);

        $this->assertSame([
            ['1', (string) $leads[2]->getId(), ''], // Only the contact without DNC
        ], $crawlerReportTable);
    }

    public function testLeadReportWithDncListFilterNotEmpty(): void
    {
        $leads[] = $this->createContact('test1@example.com');
        $leads[] = $this->createContact('test2@example.com');
        $leads[] = $this->createContact('test3@example.com');
        $this->em->flush();

        // Add DNC for first two contacts
        $this->createDnc('email', $leads[0], DoNotContact::BOUNCED);
        $this->createDnc('email', $leads[1], DoNotContact::MANUAL);
        $this->em->flush();

        $report = $this->createReport();
        $report->setFilters([
            [
                'column'    => 'dnc',
                'glue'      => 'and',
                'dynamic'   => null,
                'condition' => 'notEmpty',
                'value'     => [], // Empty value as we're looking for any contacts with DNC
            ],
        ]);
        $this->em->persist($report);
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, "/s/reports/view/{$report->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());
        $crawlerReportTable = $crawler->filterXPath('//table[@id="reportTable"]')->first();

        $crawlerReportTable = array_slice($this->domTableToArray($crawlerReportTable), 1, 2);

        $this->assertSame([
            ['1', (string) $leads[0]->getId(), 'DNC Bounced: Email'],
            ['2', (string) $leads[1]->getId(), 'DNC Manually Unsubscribed: Email'],
        ], $crawlerReportTable);
    }

    private function createReport(): Report
    {
        $report = new Report();
        $report->setName('Devices');
        $report->setSource('leads');
        $report->setColumns([
            'l.id',
            'dnc_list',
        ]);
        $this->em->persist($report);
        $this->em->flush();

        return $report;
    }

    public function createDnc(string $channel, Lead $contact, int $reason): DoNotContact
    {
        $dnc = new DoNotContact();
        $dnc->setChannel($channel);
        $dnc->setLead($contact);
        $dnc->setReason($reason);
        $dnc->setDateAdded(new \DateTime());
        $this->em->persist($dnc);

        return $dnc;
    }

    private function createContact(string $email): Lead
    {
        $lead = new Lead();
        $lead->setEmail($email);
        $this->em->persist($lead);

        return $lead;
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    private function domTableToArray(Crawler $crawler): array
    {
        return $crawler->filter('tr')->each(fn ($tr) => $tr->filter('td')->each(fn ($td) => trim($td->text())));
    }
}
