<?php

namespace Mautic\LeadBundle\Tests\Controller;

use function GuzzleHttp\json_decode;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\ProjectBundle\Entity\Project;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyControllerTest extends MauticMysqlTestCase
{
    private int $company1Id;

    private int $company2Id;
    private int $id;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $companiesData = [
            1 => [
                'name'     => 'Amazon',
                'state'    => 'Washington',
                'city'     => 'Seattle',
                'country'  => 'United States',
                'industry' => 'Goods',
            ],
            2 => [
                'name'     => 'Google',
                'state'    => 'Washington',
                'city'     => 'Seattle',
                'country'  => 'United States',
                'industry' => 'Services',
            ],
        ];

        /** @var \Mautic\LeadBundle\Model\CompanyModel $model */
        $model = self::getContainer()->get('mautic.lead.model.company');

        foreach ($companiesData as $i => $companyData) {
            $company    = new Company();
            $company->setIsPublished(true)
              ->setName($companyData['name'])
              ->setState($companyData['state'])
              ->setCity($companyData['city'])
              ->setCountry($companyData['country'])
              ->setIndustry($companyData['industry']);
            $model->saveEntity($company);

            $this->{'company'.$i.'Id'} = $company->getId();
        }
    }

    /**
     * Get company's view page.
     */
    public function testViewActionCompany(): void
    {
        $crawler                = $this->client->request('GET', '/s/companies/view/'.$this->company1Id);
        $clientResponse         = $this->client->getResponse();
        $clientResponseContent  = $clientResponse->getContent();
        $model                  = self::getContainer()->get('mautic.lead.model.company');
        $company                = $model->getEntity($this->company1Id);
        $this->assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());
        $this->assertStringContainsString($company->getName(), $clientResponseContent, 'The return must contain the name of company');
        $this->assertSame('', trim($crawler->filter('#company_contact_engagement')->text()));
        $this->assertSame('', trim($crawler->filter('#contacts-table')->text()));
    }

    public function testCompanyViewGraph(): void
    {
        $this->createLead();
        $segment = $this->createSegment();
        $this->runCommand('mautic:segments:update', ['--list-id' => $segment->getId()]);
        $crawler  = $this->client->request('GET', sprintf('s/company/graph/%d', $this->id));
        $response = $this->client->getResponse();
        self::assertTrue($response->isOk());
        $body           = json_decode($response->getContent(), true);
        $crawler        = new Crawler($body['newContent']);
        $canvasJson     = trim($crawler->filter('canvas')->html());
        $canvasData     = json_decode($canvasJson, true);
        $datasets       = $canvasData['datasets'] ?? [];
        $engagementData = $datasets[0]['data'] ?? [];
        $totalContacts  = array_sum($engagementData);

        self::assertStringContainsString('Engagements', $response->getContent());
        self::assertSame(1, $totalContacts);
    }

    /**
     * Get company's edit page.
     */
    public function testEditActionCompany(): void
    {
        $crawler                = $this->client->request('GET', '/s/companies/edit/'.$this->company1Id);
        $clientResponse         = $this->client->getResponse();
        $clientResponseContent  = $clientResponse->getContent();
        $model                  = self::getContainer()->get('mautic.lead.model.company');
        $company                = $model->getEntity($this->company1Id);
        $this->assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());
        $this->assertStringContainsString('Edit Company '.$company->getName(), $clientResponseContent, 'The return must contain \'Edit Company\' text');

        $buttonCrawler = $crawler->selectButton('Save & Close');
        $form          = $buttonCrawler->form();
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isOk());
        $this->assertMatchesRegularExpression('/\/s\/companies\/view\/'.$this->id.'/', $this->client->getRequest()->getUri());
    }

    public function testEditAndCancelActionCompany(): void
    {
        $crawler = $this->client->request('GET', '/s/companies/edit/'.$this->company1Id);
        $this->assertTrue($this->client->getResponse()->isOk());
        $buttonCrawler = $crawler->selectButton('Cancel');
        $form          = $buttonCrawler->form();
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isOk());
        $this->assertMatchesRegularExpression('/\/s\/companies\/view\/'.$this->company1Id.'/', $this->client->getRequest()->getUri());
    }

    /* Get company contacts list */
    public function testListCompanyContacts(): void
    {
        /** @var \Mautic\LeadBundle\Model\CompanyModel $companyModel */
        $companyModel = self::getContainer()->get('mautic.lead.model.company');
        $leadModel    = self::getContainer()->get('mautic.lead.model.lead');
        $company1     = $companyModel->getEntity($this->company1Id);

        // Create a lead linked to the first company
        $lead1    = new Lead();
        $lead1
          ->setFirstname('lead')
          ->setLastname('for '.$company1->getName());
        $leadModel->saveEntity($lead1);

        $companyModel->addLeadToCompany($company1, $lead1);

        // Create a lead not linked to a company
        $lead2    = new Lead();
        $lead2
          ->setFirstname('lead')
          ->setLastname('without company');
        $leadModel->saveEntity($lead2);

        // Create a lead not linked to a company, but with `ids` in it's name (see https://github.com/mautic/mautic/issues/12415)
        $lead3    = new Lead();
        $lead3
          ->setFirstname('lead')
          ->setLastname('without company')
          ->setEmail('example@idstart.com');
        $leadModel->saveEntity($lead3);

        $crawler        = $this->client->request('GET', '/s/company/'.$this->company1Id.'/contacts/');
        $leadsTableRows = $crawler->filterXPath("//table[@id='leadTable']//tbody//tr");

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, $leadsTableRows->count(), $crawler->html());

        $this->assertStringContainsString('test@test.com', $clientResponse->getContent());
        $this->assertStringContainsString('/s/contacts/view/'.$lead1->getId(), $clientResponse->getContent());
        $this->assertStringContainsString('1 item', $clientResponse->getContent());

        $crawler         = $this->client->request('GET', '/s/company/'.$this->company2Id.'/contacts/');
        $leadsTableRows  = $crawler->filterXPath("//table[@id='leadTable']//tbody//tr");

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(0, $leadsTableRows->count(), $crawler->html());
    }

    /**
     * Get company's create page.
     */
    public function testNewActionCompany(): void
    {
        $this->client->request('GET', '/s/companies/new/');
        $clientResponse         = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());
    }

    public function testNonExitingCompanyIsRedirected(): void
    {
        $this->client->followRedirects(false);
        $this->client->request(
            Request::METHOD_GET,
            's/companies/view/1000',
        );
        $this->assertEquals(true, $this->client->getResponse()->isRedirect('/s/companies'));
    }

    public function testNewCompanyMergeButtonVisible(): void
    {
        $this->client->request('GET', '/s/companies/new/');
        $clientResponse         = $this->client->getResponse();
        $clientResponseContent  = $clientResponse->getContent();
        $this->assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());

        // Use the Crawler to parse the HTML content
        $crawler = new Crawler($clientResponseContent);

        // Check for specific buttons by their IDs
        $applyButton  = $crawler->filter('#company_buttons_apply');
        $saveButton   = $crawler->filter('#company_buttons_save');
        $cancelButton = $crawler->filter('#company_buttons_cancel');
        $mergeButton  = $crawler->filter('#company_buttons_merge');

        $this->assertCount(1, $applyButton, 'Apply button not found');
        $this->assertCount(1, $saveButton, 'Save button not found');
        $this->assertCount(1, $cancelButton, 'Cancel button not found');
        $this->assertCount(0, $mergeButton, 'Merge button found');
    }

    public function testCompanyWithProject(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $this->em->persist($project);

        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/s/companies/edit/'.$this->company1Id);
        $form    = $crawler->selectButton('Save')->form();
        $form['company[projects]']->setValue((string) $project->getId());

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();

        $savedCompany = $this->em->find(Company::class, $this->company1Id);
        $this->assertSame($project->getId(), $savedCompany->getProjects()->first()->getId());
    }

    protected function createLead(): Lead
    {
        $lead = new Lead();
        $lead->setFirstname('Firstname');
        $lead->setLastname('Lastname');
        $lead->setEmail('test@test.com');
        $lead->setPhone('555-666-777');
        $this->em->persist($lead);
        $this->em->flush();

        $companyModel = self::$container->get('mautic.lead.model.company');
        $companyModel->addLeadToCompany($this->company, $lead);

        $lead->setCompany($this->company->getName());

        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    private function createSegment(): LeadList
    {
        $filters = [
            [
                'glue'     => 'and',
                'field'    => 'email',
                'object'   => 'lead',
                'type'     => 'email',
                'filter'   => null,
                'display'  => null,
                'operator' => '!empty',
            ],
        ];

        $segment = new LeadList();
        $segment->setFilters($filters);
        $segment->setName('Segment A');
        $segment->setAlias('segment-a');
        $this->em->persist($segment);
        $this->em->flush();

        return $segment;
    }
}
