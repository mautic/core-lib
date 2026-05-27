<?php

declare(strict_types=1);

namespace Mautic\PageBundle\Tests\Functional\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Entity\HitRepository;
use Mautic\PageBundle\Entity\Page;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

final class PageHitCookieTest extends MauticMysqlTestCase
{
    private HitRepository $hitRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hitRepository = $this->em->getRepository(Hit::class);
    }

    public function testPageHitCookieContainsValidHitIdAndUpdatesDateLeft(): void
    {
        // Create a page
        $page = new Page();
        $page->setIsPublished(true);
        $page->setDateAdded(new \DateTime());
        $page->setTitle('Test Page for Cookie');
        $page->setAlias('test-page-cookie');
        $page->setTemplate('Blank');
        $page->setCustomHtml('<h1>Test</h1>');
        $page->setLanguage('en');
        $this->em->persist($page);
        $this->em->flush();

        // First hit - should create a hit and set cookie with hit ID
        $this->logoutUser();
        $this->client->request(Request::METHOD_GET, '/test-page-cookie');
        $this->assertResponseIsSuccessful();

        // Verify the cookie was set
        $cookieJar   = $this->client->getCookieJar();
        $cookie      = $cookieJar->get('mautic_referer_id');
        Assert::assertNotNull($cookie, 'Cookie mautic_referer_id should be set');

        $cookieValue = $cookie->getValue();
        Assert::assertNotNull($cookieValue, 'Cookie value should not be null');
        Assert::assertIsNumeric($cookieValue, 'Cookie value should be numeric (the Hit ID)');

        // Verify the first hit was created
        $hits = $this->hitRepository->findBy(['page' => $page->getId()], ['dateHit' => 'ASC']);
        Assert::assertCount(1, $hits);
        $firstHit = $hits[0];
        Assert::assertNull($firstHit->getDateLeft(), 'First hit should not have date_left set yet');
        Assert::assertEquals((int) $cookieValue, $firstHit->getId(), 'Cookie should contain the first hit ID');

        // Second hit - should update first hit's date_left
        $this->client->request(Request::METHOD_GET, '/test-page-cookie');
        $this->assertResponseIsSuccessful();

        // Refresh the first hit from database
        $this->em->clear();
        $firstHitRefreshed = $this->hitRepository->find($firstHit->getId());

        Assert::assertNotNull(
            $firstHitRefreshed->getDateLeft(),
            'First hit should have date_left updated after second hit'
        );
        Assert::assertInstanceOf(
            \DateTimeInterface::class,
            $firstHitRefreshed->getDateLeft(),
            'date_left should be a DateTime object'
        );

        // Verify second hit was created
        $allHits = $this->hitRepository->findBy(['page' => $page->getId()], ['dateHit' => 'ASC']);
        Assert::assertCount(2, $allHits, 'Should have two hits after second page visit');
    }
}
