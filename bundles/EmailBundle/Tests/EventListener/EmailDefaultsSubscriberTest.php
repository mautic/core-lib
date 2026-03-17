<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailEvent;
use Mautic\EmailBundle\EventListener\EmailDefaultsSubscriber;
use Mautic\PageBundle\Entity\Page;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmailDefaultsSubscriberTest extends TestCase
{
    private MockObject&CoreParametersHelper $coreParametersHelper;

    private MockObject&EntityManagerInterface $entityManager;

    private EmailDefaultsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $this->entityManager        = $this->createMock(EntityManagerInterface::class);
        $this->subscriber           = new EmailDefaultsSubscriber(
            $this->coreParametersHelper,
            $this->entityManager,
        );
    }

    public function testAppliesDefaultsForNewEmail(): void
    {
        $page = new Page();
        $page->setTitle('Default PC');

        $this->coreParametersHelper->method('get')->willReturnMap([
            ['email_default_preference_center_id', null, 42],
            ['email_default_utm_source', null, 'config-source'],
            ['email_default_utm_medium', null, 'config-medium'],
            ['email_default_utm_campaign', null, 'config-campaign'],
            ['email_default_utm_content', null, 'config-content'],
        ]);

        $this->entityManager->method('find')
            ->with(Page::class, 42)
            ->willReturn($page);

        $email = new Email();
        $event = new EmailEvent($email, true);

        $this->subscriber->onEmailPreSave($event);

        $this->assertSame($page, $email->getPreferenceCenter());
        $this->assertSame([
            'utmSource'   => 'config-source',
            'utmMedium'   => 'config-medium',
            'utmCampaign' => 'config-campaign',
            'utmContent'  => 'config-content',
        ], $email->getUtmTags());
    }

    public function testDoesNotOverwriteExistingValues(): void
    {
        $existingPage = new Page();
        $existingPage->setTitle('Existing PC');

        $email = new Email();
        $email->setPreferenceCenter($existingPage);
        $email->setUtmTags([
            'utmSource'   => 'existing-source',
            'utmMedium'   => 'existing-medium',
            'utmCampaign' => 'existing-campaign',
            'utmContent'  => 'existing-content',
        ]);

        $this->coreParametersHelper->method('get')->willReturnMap([
            ['email_default_preference_center_id', null, 99],
            ['email_default_utm_source', null, 'config-source'],
            ['email_default_utm_medium', null, 'config-medium'],
            ['email_default_utm_campaign', null, 'config-campaign'],
            ['email_default_utm_content', null, 'config-content'],
        ]);

        $this->entityManager->expects($this->never())->method('find');

        $event = new EmailEvent($email, true);
        $this->subscriber->onEmailPreSave($event);

        $this->assertSame($existingPage, $email->getPreferenceCenter());
        $this->assertSame('existing-source', $email->getUtmTags()['utmSource']);
    }

    public function testSkipsDefaultsForClonedEmail(): void
    {
        $source = new Email();
        $source->setName('Source');
        $source->setUtmTags(['utmSource' => 'clone-source']);

        $clone = clone $source;
        $this->assertTrue($clone->getIsClone());

        $this->coreParametersHelper->expects($this->never())->method('get');

        $event = new EmailEvent($clone, true);
        $this->subscriber->onEmailPreSave($event);

        $this->assertSame(['utmSource' => 'clone-source'], $clone->getUtmTags());
    }

    public function testSkipsDefaultsForExistingEmail(): void
    {
        $email = new Email();
        // Simulate a persisted entity by setting the ID via reflection.
        $ref = new \ReflectionProperty($email, 'id');
        $ref->setValue($email, 5);

        $this->coreParametersHelper->expects($this->never())->method('get');

        $event = new EmailEvent($email, false);
        $this->subscriber->onEmailPreSave($event);

        $this->assertNull($email->getPreferenceCenter());
        $this->assertEmpty($email->getUtmTags());
    }

    public function testLeavesFieldsUnchangedWhenConfigIsEmpty(): void
    {
        $this->coreParametersHelper->method('get')->willReturn(null);
        $this->entityManager->expects($this->never())->method('find');

        $email = new Email();
        $event = new EmailEvent($email, true);
        $this->subscriber->onEmailPreSave($event);

        $this->assertNull($email->getPreferenceCenter());
        $this->assertEmpty($email->getUtmTags());
    }

    public function testDoesNotApplyConfigDefaultsToCloneWithBlankFields(): void
    {
        $source = new Email();
        $source->setName('Source');

        $clone = clone $source;
        $this->assertTrue($clone->getIsClone());

        $this->coreParametersHelper->expects($this->never())->method('get');

        $event = new EmailEvent($clone, true);
        $this->subscriber->onEmailPreSave($event);

        $this->assertNull($clone->getPreferenceCenter());
        $this->assertEmpty($clone->getUtmTags());
    }

    public function testPreservesPreExistingChanges(): void
    {
        $email = new Email();
        $email->setName('Test Email');
        // Name change is now tracked in the entity's changes array.
        $changesBefore = $email->getChanges();
        $this->assertNotEmpty($changesBefore);

        $this->coreParametersHelper->method('get')->willReturnMap([
            ['email_default_preference_center_id', null, null],
            ['email_default_utm_source', null, 'src'],
            ['email_default_utm_medium', null, null],
            ['email_default_utm_campaign', null, null],
            ['email_default_utm_content', null, null],
        ]);

        $event = new EmailEvent($email, true);
        $this->subscriber->onEmailPreSave($event);

        $this->assertSame($changesBefore, $email->getChanges());
        $this->assertSame(['utmSource' => 'src'], $email->getUtmTags());
    }
}
