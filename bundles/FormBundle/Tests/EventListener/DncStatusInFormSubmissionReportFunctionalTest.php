<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Tests\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\ReportBundle\Entity\Report;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DncStatusInFormSubmissionReportFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback   = false;
    protected bool $authenticateApi = true;

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

        $formId = $this->createFormThroughApi();
        $this->submitForm($formId, [
            'email'     => 'test1@example.com',
            'firstname' => 'test1',
        ]);
        $this->submitForm($formId, [
            'email'     => 'test2@example.com',
            'firstname' => 'test2',
        ]);

        $report = $this->createReport();
        $report->setFilters([
            [
                'column'    => 'f.id',
                'glue'      => 'and',
                'dynamic'   => null,
                'condition' => 'eq',
                'value'     => $formId,
            ],
        ]);
        $this->em->persist($report);
        $this->em->flush();

        $crawler            = $this->client->request(Request::METHOD_GET, "/s/reports/view/{$report->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());
        $crawlerReportTable = $crawler->filterXPath('//table[@id="reportTable"]')->first();

        // convert html table to php array
        $crawlerReportTable = $this->domTableToArray($crawlerReportTable);

        $this->assertSame([
            // no., id, firstname, dnc_list
            ['1', (string) $leads[0]->getId(), 'test1', 'DNC Bounced: Email'],
            ['2', (string) $leads[1]->getId(), 'test2', 'DNC Manually Unsubscribed: Email'],
        ], $crawlerReportTable);
    }

    public function testLeadReportWithDncListFilterIn(): void
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

        $formId = $this->createFormThroughApi();
        $this->submitForm($formId, [
            'email'     => 'test1@example.com',
            'firstname' => 'test1',
        ]);
        $this->submitForm($formId, [
            'email'     => 'test2@example.com',
            'firstname' => 'test2',
        ]);
        $this->submitForm($formId, [
            'email'     => 'test3@example.com',
            'firstname' => 'test3',
        ]);

        $report = $this->createReport();
        $report->setFilters([
            [
                'column'    => 'f.id',
                'glue'      => 'and',
                'dynamic'   => null,
                'condition' => 'eq',
                'value'     => $formId,
            ],
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
        $crawlerReportTable = $this->domTableToArray($crawlerReportTable);

        $this->assertSame([
            // no., id, firstname, dnc_list
            ['1', (string) $leads[0]->getId(), 'test1', 'DNC Bounced: Email'],
            ['2', (string) $leads[2]->getId(), 'test3', 'DNC Manually Unsubscribed: Text Message, DNC Unsubscribed: Email'],
        ], $crawlerReportTable);
    }

    private function createFormThroughApi(): int
    {
        $formPayload = [
            'name'        => 'Submission test form',
            'description' => 'Form created via submission test',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => [
                [
                    'label'        => 'Email',
                    'type'         => 'email',
                    'alias'        => 'email',
                    'leadField'    => 'email',
                    'mappedField'  => 'email',
                    'mappedObject' => 'contact',
                ],
                [
                    'label'        => 'Firstname',
                    'type'         => 'text',
                    'alias'        => 'firstname',
                    'leadField'    => 'firstname',
                    'mappedField'  => 'firstname',
                    'mappedObject' => 'contact',
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'postAction'  => 'return',
        ];

        // Create the form
        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $formId         = (int) $response['form']['id'];

        $this->assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());

        return $formId;
    }

    private function submitForm(int $formId, array $submissionData): Crawler
    {
        // Submit the form
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$formId}");
        $formCrawler = $crawler->filter('form[id=mauticform_submissiontestform]');
        $this->assertCount(1, $formCrawler);
        $form = $formCrawler->form();

        $formData = [];
        foreach ($submissionData as $key => $value) {
            $formData["mauticform[{$key}]"] = $value;
        }
        $form->setValues($formData);

        return $this->client->submit($form);
    }

    private function createReport(): Report
    {
        $report = new Report();
        $report->setName('Form submission');
        $report->setSource('form.submissions');
        $report->setColumns([
            'l.id',
            'l.firstname',
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
        $table = $crawler->filter('tr')->each(fn ($tr) => $tr->filter('td')->each(fn ($td) => trim($td->text())));
        array_shift($table);
        array_pop($table);

        return $table;
    }
}
