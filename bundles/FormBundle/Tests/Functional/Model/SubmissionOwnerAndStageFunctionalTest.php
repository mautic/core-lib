<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Tests\Functional\Model;

use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\Mapping\MappingException;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Submission;
use Mautic\StageBundle\Entity\Stage;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SubmissionOwnerAndStageFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback   = false;

    /**
     * @param string[] $submissionDataPlaceholders
     *
     * @throws NotSupported
     * @throws ORMException
     * @throws MappingException
     */
    #[DataProvider('ownerAndStageDataProvider')]
    public function testSubmissionSetsOwnerAndStage(
        string $testName,
        string $contactEmail,
        array $submissionDataPlaceholders,
        ?string $expectedOwnerUsername = null,
        ?string $expectedStageName = null,
    ): void {
        $salesUser = $this->em->getRepository(User::class)->findOneBy(['username' => 'sales']);
        $adminUser = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        $stage = new Stage();
        $stage->setName('Test Stage');
        $this->em->persist($stage);
        $this->em->flush();
        $this->em->clear();

        $submissionData = $this->replacePlaceholders($submissionDataPlaceholders, [
            '%sales_user_email%' => $salesUser->getEmail(),
            '%admin_user_id%'    => (string) $adminUser->getId(),
            '%stage_name%'       => $stage->getName(),
        ]);

        $expectedOwnerId = null;
        if ($expectedOwnerUsername) {
            $expectedOwner   = $this->em->getRepository(User::class)->findOneBy(['username' => $expectedOwnerUsername]);
            $expectedOwnerId = $expectedOwner->getId();
        }

        $expectedStageId = null;
        if ($expectedStageName) {
            $expectedStage   = $this->em->getRepository(Stage::class)->findOneBy(['name' => $expectedStageName]);
            $expectedStageId = $expectedStage->getId();
        }

        $payload = [
            'name'        => 'Form test',
            'alias'       => 'formtest',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => $this->createFormFields(),
            'postAction'  => 'return',
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $payload);
        $clientResponse = $this->client->getResponse();
        $this->assertSame(Response::HTTP_CREATED, $clientResponse->getStatusCode(), $clientResponse->getContent());
        $response = json_decode($clientResponse->getContent(), true);
        $formId   = $response['form']['id'];

        $crawler = $this->client->request(Request::METHOD_GET, "/form/{$formId}");
        $form    = $crawler->filter('form[id=mauticform_formtest]')->form();

        $formValues = [];
        foreach ($submissionData as $key => $value) {
            $formValues['mauticform['.$key.']'] = $value;
        }

        $this->client->submit($form, $formValues);

        $submissions = $this->em->getRepository(Submission::class)->findBy(['form' => $formId]);
        $this->assertCount(1, $submissions, "Submission was not created for test: {$testName}");

        /** @var Submission $submission */
        $submission = $submissions[0];
        $contact    = $submission->getLead();

        $this->assertNotNull($contact, "Contact was not created for test: {$testName}");
        $this->assertSame($contactEmail, $contact->getEmail());

        if ($expectedOwnerId) {
            $this->assertNotNull($contact->getOwner(), "Owner not set for test: {$testName}");
            $this->assertSame($expectedOwnerId, $contact->getOwner()->getId(), "Incorrect owner set for test: {$testName}");
        } else {
            $this->assertNull($contact->getOwner(), "Owner was set unexpectedly for test: {$testName}");
        }

        if ($expectedStageId) {
            $this->assertNotNull($contact->getStage(), "Stage not set for test: {$testName}");
            $this->assertSame($expectedStageId, $contact->getStage()->getId(), "Incorrect stage set for test: {$testName}");
        } else {
            $this->assertNull($contact->getStage(), "Stage was set unexpectedly for test: {$testName}");
        }
    }

    /**
     * @return iterable<string, array<string, string|string[]|null>>
     */
    public static function ownerAndStageDataProvider(): iterable
    {
        yield 'owner by email' => [
            'testName'                     => 'owner by email',
            'contactEmail'                 => 'contact.owner.email@test.com',
            'submissionDataPlaceholders'   => [
                'email'          => 'contact.owner.email@test.com',
                'owner_by_email' => '%sales_user_email%',
            ],
            'expectedOwnerUsername'        => 'sales',
            'expectedStageName'            => null,
        ];

        yield 'owner by id' => [
            'testName'                     => 'owner by id',
            'contactEmail'                 => 'contact.owner.id@test.com',
            'submissionDataPlaceholders'   => [
                'email'       => 'contact.owner.id@test.com',
                'owner_by_id' => '%sales_user_email%',
            ],
            'expectedOwnerUsername'        => 'sales',
            'expectedStageName'            => null,
        ];

        yield 'stage' => [
            'testName'                     => 'stage',
            'contactEmail'                 => 'contact.stage.id@test.com',
            'submissionDataPlaceholders'   => [
                'email' => 'contact.stage.id@test.com',
                'stage' => '%stage_name%',
            ],
            'expectedOwnerUsername'        => null,
            'expectedStageName'            => 'Test Stage',
        ];

        yield 'owner by email and stage' => [
            'testName'                     => 'owner by email and stage',
            'contactEmail'                 => 'contact.owner.email.stage@test.com',
            'submissionDataPlaceholders'   => [
                'email'          => 'contact.owner.email.stage@test.com',
                'owner_by_email' => '%sales_user_email%',
                'stage'          => '%stage_name%',
            ],
            'expectedOwnerUsername'        => 'sales',
            'expectedStageName'            => 'Test Stage',
        ];

        yield 'owner by id and stage' => [
            'testName'                     => 'owner by id and stage',
            'contactEmail'                 => 'contact.owner.id.stage@test.com',
            'submissionDataPlaceholders'   => [
                'email'       => 'contact.owner.id.stage@test.com',
                'owner_by_id' => '%sales_user_email%',
                'stage'       => '%stage_name%',
            ],
            'expectedOwnerUsername'        => 'sales',
            'expectedStageName'            => 'Test Stage',
        ];

        yield 'owner by email and id (email has precedence)' => [
            'testName'                     => 'owner by email and id',
            'contactEmail'                 => 'contact.owner.email.id@test.com',
            'submissionDataPlaceholders'   => [
                'email'          => 'contact.owner.email.id@test.com',
                'owner_by_email' => '%sales_user_email%',
                'owner_by_id'    => '%admin_user_id%',
            ],
            'expectedOwnerUsername'        => 'sales',
            'expectedStageName'            => null,
        ];

        yield 'invalid owner email' => [
            'testName'                     => 'invalid owner email',
            'contactEmail'                 => 'contact.invalid.owner.email@test.com',
            'submissionDataPlaceholders'   => [
                'email'          => 'contact.invalid.owner.email@test.com',
                'owner_by_email' => 'nonexistent@email.com',
            ],
            'expectedOwnerUsername'        => null,
            'expectedStageName'            => null,
        ];

        yield 'invalid owner id' => [
            'testName'                     => 'invalid owner id',
            'contactEmail'                 => 'contact.invalid.owner.id@test.com',
            'submissionDataPlaceholders'   => [
                'email'       => 'contact.invalid.owner.id@test.com',
                'owner_by_id' => '99999',
            ],
            'expectedOwnerUsername'        => null,
            'expectedStageName'            => null,
        ];

        yield 'invalid stage name' => [
            'testName'                     => 'invalid stage name',
            'contactEmail'                 => 'contact.invalid.stage.id@test.com',
            'submissionDataPlaceholders'   => [
                'email' => 'contact.invalid.stage.id@test.com',
                'stage' => 'mautic',
            ],
            'expectedOwnerUsername'        => null,
            'expectedStageName'            => null,
        ];

        yield 'empty owner and stage' => [
            'testName'                   => 'empty owner and stage',
            'contactEmail'               => 'contact.empty.fields@test.com',
            'submissionDataPlaceholders' => [
                'email'          => 'contact.empty.fields@test.com',
                'owner_by_email' => '',
                'stage'          => '',
            ],
            'expectedOwnerUsername' => null,
            'expectedStageName'     => null,
        ];
    }

    /**
     * @return array<int, string[]>
     */
    private function createFormFields(): array
    {
        return [
            ['label' => 'Email', 'type' => 'email', 'alias' => 'email', 'leadField' => 'email'],
            ['label' => 'Owners Email', 'type' => 'text', 'alias' => 'owner_by_email', 'leadField' => 'owner'],
            ['label' => 'Owners id', 'type' => 'text', 'alias' => 'owner_by_id', 'leadField' => 'ownerbyid'],
            ['label' => 'Stage', 'type' => 'text', 'alias' => 'stage', 'leadField' => 'contact_stage'],
            ['label' => 'Submit', 'type' => 'button'],
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $replacements
     *
     * @return array<string, string>
     */
    private function replacePlaceholders(array $data, array $replacements): array
    {
        return array_map(function ($value) use ($replacements) {
            return str_replace(array_keys($replacements), array_values($replacements), $value);
        }, $data);
    }
}
