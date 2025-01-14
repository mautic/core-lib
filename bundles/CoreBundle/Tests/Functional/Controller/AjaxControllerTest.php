<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Tests\Functional\Controller;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mautic\ApiBundle\Entity\oAuth2\Client;
use Mautic\AssetBundle\Entity\Asset;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\ChannelBundle\Entity\Channel;
use Mautic\ChannelBundle\Entity\Message;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\DynamicContentBundle\Entity\DynamicContent;
use Mautic\EmailBundle\Entity\Email;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\NotificationBundle\Entity\Notification;
use Mautic\PageBundle\Entity\Page;
use Mautic\PointBundle\Entity\Point;
use Mautic\PointBundle\Entity\Trigger;
use Mautic\ReportBundle\Entity\Report;
use Mautic\SmsBundle\Entity\Sms;
use Mautic\StageBundle\Entity\Stage;
use Mautic\UserBundle\Entity\Permission;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class AjaxControllerTest extends MauticMysqlTestCase
{
    /**
     * @var MockHandler
     */
    private $clientMockHandler;

    protected function setUp(): void
    {
        $this->configParams['composer_updates'] = 'testUpdateRunChecksAction' !== $this->getName();

        parent::setUp();

        $this->clientMockHandler = static::getContainer()->get(MockHandler::class);
    }

    public function testUpdateRunChecksAction(): void
    {
        $responseToPostUpdate  = new Response();
        $responseToGetUpdate   = new Response(200, [], file_get_contents(__DIR__.'/../../Fixtures/releases.json'));
        $responseToGetMetadata = new Response(200, [], file_get_contents(__DIR__.'/../../Fixtures/metadata.json'));

        $this->clientMockHandler->append($responseToPostUpdate, $responseToGetUpdate, $responseToGetMetadata);

        $this->client->request('GET', 's/ajax?action=core:updateRunChecks');
        $response = $this->client->getResponse();
        self::assertResponseIsSuccessful($response->getContent());
        Assert::assertStringContainsString('Great! You are running the current version of Mautic.', $response->getContent());
    }

    /**
     * @dataProvider dataForGlobalSearch
     */
    public function testGlobalSearch(string $searchString, mixed $entity, string $expectedLink): void
    {
        $this->em->persist($entity);

        $this->em->flush();
        $this->em->clear();

        $this->client->xmlHttpRequest(Request::METHOD_GET, '/s/ajax?action=globalSearch&global_search='.$searchString.'&tmp=list');
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk());

        $content      = \json_decode($response->getContent(), true);
        $expectedLink = rtrim($expectedLink, '/').'/'.$entity->getId();

        $this->assertStringContainsString($expectedLink, $content['newContent']);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public function dataForGlobalSearch(): iterable
    {
        // Api
        $apiClient = $this->getApiClient();

        yield 'Search API' => [
            'Client',
            $apiClient,
            's/credentials/view/',
        ];

        // Asset
        $asset = $this->getAsset();

        yield 'Search Asset' => [
            'asset',
            $asset,
            's/assets/view/',
        ];

        // Campaign
        $campaign = $this->getCampaign();

        yield 'Search Campaign' => [
            'campaign',
            $campaign,
            's/campaigns/view/',
        ];

        // Channel: Marketing Messages
        $message = $this->getMessage();

        yield 'Search Marketing Messages' => [
            'marketing',
            $message,
            's/messages/view/',
        ];

        // Dynamic Content
        $dwc = $this->getDynamicContent();

        yield 'Search Dynamic Content' => [
            'dynamic',
            $dwc,
            's/dwc/view/',
        ];

        // Email
        $email = $this->getEmail();

        yield 'Search Email' => [
            'email',
            $email,
            's/emails/view/',
        ];

        // Form
        $form = $this->getForm();

        yield 'Search Forms' => [
            'form',
            $form,
            's/forms/view/',
        ];

        // Lead
        $lead = $this->getLead();

        yield 'Search Contact' => [
            'john',
            $lead,
            's/contacts/view/',
        ];

        // Companies
        $company = $this->getCompany();

        yield 'Search Companies' => [
            'maut',
            $company,
            's/companies/view/',
        ];

        // Segment
        $segment = $this->getSegment();

        yield 'Search Segment' => [
            'news',
            $segment,
            's/segments/view/',
        ];

        // Notification Mobile
        $notification = $this->getNotification('Notification Mobile', true);

        yield 'Search Notification Mobile' => [
            'mobile',
            $notification,
            's/mobile_notifications/edit/',
        ];

        // Notification Web
        $notification = $this->getNotification('Notification Web');

        yield 'Search Notification Web' => [
            'web',
            $notification,
            's/notifications/view/',
        ];

        // Page
        $page = $this->getPage();

        yield 'Search Page' => [
            'landing',
            $page,
            's/pages/view/',
        ];

        // Point Action
        $pointAction = $this->getPointAction();

        yield 'Search Point Action' => [
            'action',
            $pointAction,
            's/points/edit/',
        ];

        // Point Trigger
        $pointTrigger = $this->getPointTrigger();

        yield 'Search Point Trigger' => [
            'trigger',
            $pointTrigger,
            's/triggers/edit/',
        ];

        // Report
        $report = $this->getReport();

        yield 'Search Report' => [
            'lead',
            $report,
            's/reports/view/',
        ];

        // SMS
        $sms = new Sms();
        $sms->setName('Welcome text');
        $sms->setMessage('Hello there!');

        yield 'Search SMS' => [
            'welcome',
            $sms,
            's/sms/view/',
        ];

        // Stage
        $stage = $this->getStage();

        yield 'Search Stage' => [
            'stage',
            $stage,
            's/stages/edit/',
        ];

        // Role
        $role = new Role();
        $role->setName('Editor');

        yield 'Search Role' => [
            'edit',
            $role,
            's/roles/edit/',
        ];
    }

    public function testGlobalSearchForUser(): void
    {
        $user = $this->createUser([
            'user-name'     => 'user',
            'email'         => 'user@mautic-test.com',
            'first-name'    => 'user',
            'last-name'     => 'user',
            'role'          => [
                'name'      => 'perm',
                'perm'      => 'api:clients',
                'bitwise'   => 32, // just create
            ],
        ]);

        $this->em->clear();

        $this->client->xmlHttpRequest(Request::METHOD_GET, '/s/ajax?action=globalSearch&global_search=user&tmp=list');
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk());

        $content = \json_decode($response->getContent(), true);
        $this->assertStringContainsString('s/users/edit/'.$user->getId(), $content['newContent']);
    }

    /**
     * @dataProvider dataForGlobalSearchForNonAdminUser
     *
     * @param array<string, string|int> $roleData
     */
    public function testGlobalSearchForNonAdminUser(
        string $searchString,
        mixed $entity,
        array $roleData,
        string $notExpectedLink,
    ): void {
        $this->em->persist($entity);

        $this->em->flush();
        $this->em->clear();

        $user = $this->createUser([
            'user-name'     => 'user-view-own',
            'email'         => 'user-view-own@mautic-test.com',
            'first-name'    => 'user-view-own',
            'last-name'     => 'user-view-own',
            'role'          => $roleData,
        ]);

        $this->loginOtherUser($user->getUserIdentifier());

        $this->client->xmlHttpRequest(Request::METHOD_GET, '/s/ajax?action=globalSearch&global_search='.$searchString.'&tmp=list');

        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk(), $response->getContent());
        $content = \json_decode($response->getContent(), true);
        $this->assertArrayHasKey('newContent', $content);

        $this->assertMatchesRegularExpression(
            '/<div.*?>\s*'.
            '<div.*?>\n'.
            '\s*<span class="fs-16">Navigate<\/span>\n'.
            '\s*<kbd><i class="ri-arrow-up-line"><\/i><\/kbd>\n'.
            '\s*<kbd><i class="ri-arrow-down-line"><\/i><\/kbd>\n'.
            '\s*<span class="fs-16">or<\/span>\n'.
            '\s*<kbd>tab<\/kbd>\n'.
            '\s*<\/div>\n'.
            '\s*<div.*?>\n'.
            '\s*<span class="fs-16">Close<\/span>\n'.
            '\s*<kbd>esc<\/kbd>\n'.
            '\s*<\/div>\n'.
            '\s*<\/div>\n\n'.
            '<div class="pa-sm" id="globalSearchPanel">\n'. // Matches the search panel div
            '\s*<!-- No results message -->\n'. // Matches the "No results message" comment
            '\s*<div class="text-center text-secondary mt-sm">\n'. // Matches the no-results div
            '\s*.*?\n'. // Matches random text inside the no-results div
            '\s*<\/div>\n'.
            '\s*<\/div>/s',
            $content['newContent']
        );

        $this->assertStringNotContainsString($notExpectedLink, $content['newContent']);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public function dataForGlobalSearchForNonAdminUser(): iterable
    {
        $apiClient = $this->getApiClient();

        yield 'Search API' => [
            // Search string
            'client',
            // Object
            $apiClient,
            // role
            [
                'name'      => 'perm_user_view',
                'perm'      => 'api:clients',
                'bitwise'   => 32, // just create
            ],
            // link
            's/credentials/view/',
        ];

        $asset = $this->getAsset();
        yield 'Search Asset' => [
            // Search string
            'asset',
            // Object
            $asset,
            // role
            [
                'name'      => 'perm_edit_own',
                'perm'      => 'asset:assets',
                'bitwise'   => 42, // just viewown, editown, create
            ],
            // link
            's/credentials/view/',
        ];
    }

    private function loginOtherUser(string $name): void
    {
        $this->client->request(Request::METHOD_GET, '/s/logout');
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $name]);

        $this->loginUser($user);
        $this->client->setServerParameter('PHP_AUTH_USER', $name);
        $this->client->setServerParameter('PHP_AUTH_PW', 'Maut1cR0cks!');
    }

    /**
     * @param array<string, mixed> $userDetails
     */
    private function createUser(array $userDetails): User
    {
        $role = new Role();
        $role->setName($userDetails['role']['name']);
        $role->setIsAdmin(false);

        $this->em->persist($role);

        $this->createPermission($role, $userDetails['role']['perm'], $userDetails['role']['bitwise']);

        $user = new User();
        $user->setEmail($userDetails['email']);
        $user->setUsername($userDetails['user-name']);
        $user->setFirstName($userDetails['first-name']);
        $user->setLastName($userDetails['last-name']);
        $user->setRole($role);

        $hasher = self::getContainer()->get('security.password_hasher_factory')->getPasswordHasher($user);
        \assert($hasher instanceof PasswordHasherInterface);
        $user->setPassword($hasher->hash('Maut1cR0cks!'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createPermission(Role $role, string $rawPermission, int $bitwise): void
    {
        $parts      = explode(':', $rawPermission);
        $permission = new Permission();
        $permission->setBundle($parts[0]);
        $permission->setName($parts[1]);
        $permission->setRole($role);
        $permission->setBitwise($bitwise);
        $this->em->persist($permission);
    }

    private function getApiClient(): Client
    {
        $apiClient = new Client();
        $apiClient->setName('Client');
        $apiClient->setRedirectUris(['https://example.com/testing']);

        return $apiClient;
    }

    private function getAsset(): Asset
    {
        $asset = new Asset();
        $asset->setTitle('Asset');
        $asset->setAlias('asset');

        return $asset;
    }

    private function getCampaign(): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('Campaign');

        return $campaign;
    }

    private function getMessage(): Message
    {
        $channel = new Channel();
        $channel->setChannel('email');
        $channel->setChannelId(12);
        $channel->setIsEnabled(true);

        $message = new Message();
        $message->setName('Marketing Message');
        $message->setDescription('random text string for description');
        $message->addChannel($channel);

        return $message;
    }

    private function getDynamicContent(): DynamicContent
    {
        $dwc = new DynamicContent();
        $dwc->setName('Dynamic Content');

        return $dwc;
    }

    private function getEmail(): Email
    {
        $email = new Email();
        $email->setName('Email');
        $email->setSubject('Subject');
        $email->setIsPublished(true);

        return $email;
    }

    private function getForm(): Form
    {
        $form = new Form();
        $form->setName('Forms');
        $form->setAlias('form');

        return $form;
    }

    private function getLead(): Lead
    {
        $lead = new Lead();
        $lead->setFirstname('John');

        return $lead;
    }

    private function getCompany(): Company
    {
        $company = new Company();
        $company->setName('Mautic');

        return $company;
    }

    private function getSegment(): LeadList
    {
        $segment = new LeadList();
        $segment->setAlias('newsletter');
        $segment->setName('Newsletter');
        $segment->setPublicName('Newsletter');
        $segment->setFilters([]);

        return $segment;
    }

    private function getNotification(string $name, bool $isMobile = false): Notification
    {
        $notification = new Notification();
        $notification->setName($name);
        $notification->setHeading('Heading 1');
        $notification->setMessage('Message 1');
        $notification->setMobile($isMobile);

        return $notification;
    }

    private function getPage(): Page
    {
        $page = new Page();
        $page->setTitle('Landing Page');
        $page->setAlias('landing-page');

        return $page;
    }

    private function getPointAction(): Point
    {
        $pointAction = new Point();
        $pointAction->setName('Read email action');
        $pointAction->setDelta(1);
        $pointAction->setType('email.open');
        $pointAction->setProperties(['emails' => [1]]);

        return $pointAction;
    }

    private function getPointTrigger(): Trigger
    {
        $pointTrigger = new Trigger();
        $pointTrigger->setName('Trigger');

        return $pointTrigger;
    }

    private function getReport(): Report
    {
        $report = new Report();
        $report->setName('Lead and points');
        $report->setSource('lead.pointlog');

        return $report;
    }

    private function getStage(): Stage
    {
        $stage = new Stage();
        $stage->setName('Stage');

        return $stage;
    }
}
