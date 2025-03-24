<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class SearchTestHelper extends MauticMysqlTestCase
{
    /**
     * @param array<string, string|array<string, string>> $data
     */
    protected function createContact(array $data): void
    {
        /** @var LeadModel $leadModel */
        $leadModel = static::getContainer()->get('mautic.lead.model.lead');

        $contact = (new Lead())
            ->setFirstname($data['firstname'])
            ->setLastname($data['lastname'])
            ->setEmail($data['email'])
            ->setCompany($data['company']);

        foreach ($data['customFields'] ?? [] as $key => $value) {
            $contact->addUpdatedField($key, $value);
        }

        $leadModel->saveEntity($contact);
    }

    protected function performSearch(string $url): Response
    {
        $this->client->xmlHttpRequest(Request::METHOD_GET, $url);
        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();

        return $response;
    }
}
