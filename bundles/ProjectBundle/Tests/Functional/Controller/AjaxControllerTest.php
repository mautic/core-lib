<?php

declare(strict_types=1);

namespace Mautic\ProjectBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\ProjectBundle\Entity\Project;
use Mautic\ProjectBundle\Model\ProjectModel;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

final class AjaxControllerTest extends MauticMysqlTestCase
{
    private const LOOKUP_CHOICE_LIST_URL  = '/s/ajax?action=project:getLookupChoiceList';

    public function testCreatingProjectViaMultiselectInput(): void
    {
        $projectNames = [
            'Yellow Project',
            'Blue Project',
            'Red Project',
        ];

        /** @var ProjectModel $projectModel */
        $projectModel = self::getContainer()->get(ProjectModel::class);

        $projects = array_map(
            static function (string $projectName) use ($projectModel) {
                $project = new Project();
                $project->setName($projectName);
                $projectModel->saveEntity($project);

                return $project;
            },
            $projectNames
        );

        $this->client->request(
            'POST',
            '/s/ajax?action=project:addProjects',
            [
                'newProjectNames'    => json_encode(['Green Project']),
                'existingProjectIds' => json_encode([$projects[0]->getId(), $projects[1]->getId()]),
            ]
        );
        $this->assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent(), true);

        Assert::assertArrayHasKey('projects', $payload);

        // The options are orderec alphabetically by name.
        Assert::assertSame(
            // The Blue Project is selected as it was sent as part of the existingProjectIds.
            '<option selected="selected" value="'.$projects[1]->getId().'">'.$projects[1]->getName().'</option>'.
            // The Green Project is selected as it was sent as part of the newProjectNames and should have next ID as it was created as 4th.
            '<option selected="selected" value="'.($projects[2]->getId() + 1).'">Green Project</option>'.
            // The Red Project is NOT selected as it was not sent in the AJAX request but it is listed as unselected option.
            '<option value="'.$projects[2]->getId().'">'.$projects[2]->getName().'</option>'.
            // The Yellow Project is selected as it was sent as part of the existingProjectIds.
            '<option selected="selected" value="'.$projects[0]->getId().'">'.$projects[0]->getName().'</option>',
            $payload['projects']
        );
    }

    public function testGetLookupChoiceListActionWithValidEntityType(): void
    {
        // Test with a known entity type that should exist in the system
        $this->client->request(
            'GET',
            self::LOOKUP_CHOICE_LIST_URL,
            ['entityType' => 'email']
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        Assert::assertIsArray($response);

        // Verify response structure - even if empty, it should be a valid array
        foreach ($response as $item) {
            Assert::assertArrayHasKey('text', $item);
            Assert::assertArrayHasKey('value', $item);
            Assert::assertIsString($item['text']);
            Assert::assertTrue(is_string($item['value']) || is_int($item['value']));
        }
    }

    public function testGetLookupChoiceListActionWithSearchKeyParameter(): void
    {
        $this->client->request(
            'GET',
            self::LOOKUP_CHOICE_LIST_URL,
            [
                'entityType'   => 'email',
                'searchKey'    => 'customSearch',
                'customSearch' => 'test_value',
            ]
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        Assert::assertIsArray($response);

        // Verify response structure
        foreach ($response as $item) {
            Assert::assertArrayHasKey('text', $item);
            Assert::assertArrayHasKey('value', $item);
        }
    }

    public function testGetLookupChoiceListActionWithPaginationParameters(): void
    {
        $this->client->request(
            'GET',
            self::LOOKUP_CHOICE_LIST_URL,
            [
                'entityType' => 'email',
                'limit'      => '5',
                'start'      => '0',
            ]
        );

        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse();

        Assert::assertSame(Response::HTTP_OK, $response->getStatusCode());
        Assert::assertSame('application/json', $response->headers->get('Content-Type'));

        $decodedResponse = json_decode($response->getContent(), true);

        Assert::assertIsArray($decodedResponse);
        Assert::assertLessThanOrEqual(5, count($decodedResponse));

        // Verify response structure
        foreach ($decodedResponse as $item) {
            Assert::assertArrayHasKey('text', $item);
            Assert::assertArrayHasKey('value', $item);
        }
    }

    public function testCreatingDuplicateProject(): void
    {
        $projectModel = self::$container->get('mautic.project.model.project');
        \assert($projectModel instanceof ProjectModel);

        $this->assertCount(
            0,
            $this->em->getRepository(Project::class)->findAll(),
            'There should be no projects at the beginning of the test.'
        );

        $project = new Project();
        $project->setName('Yellow Project');
        $projectModel->saveEntity($project);

        $this->assertCount(
            1,
            $this->em->getRepository(Project::class)->findAll(),
            'There should be 1 project after creating the first one.'
        );

        $this->client->request(
            'POST',
            '/s/ajax?action=project:addProjects',
            [
                'newProjectNames'    => json_encode(['yellow project']),
                'existingProjectIds' => json_encode([$project->getId()]),
            ]
        );

        $this->assertCount(
            1,
            $this->em->getRepository(Project::class)->findAll(),
            'There should be still 1 project after an attempt to create a duplicate project.'
        );

        $this->client->request(
            'POST',
            '/s/ajax?action=project:addProjects',
            [
                'newProjectNames'    => json_encode(['green project']),
                'existingProjectIds' => json_encode([$project->getId()]),
            ]
        );

        $this->assertCount(
            2,
            $this->em->getRepository(Project::class)->findAll(),
            'There should be 2 projects after an attempt to create a unique project.'
        );
    }
}
