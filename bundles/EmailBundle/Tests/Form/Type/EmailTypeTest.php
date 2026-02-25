<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Form\Type;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\ThemeHelperInterface;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Form\Type\EmailType;
use Mautic\EmailBundle\Helper\EmailConfigInterface;
use Mautic\PageBundle\Entity\Page;
use Mautic\StageBundle\Model\StageModel;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject&TranslatorInterface
     */
    private MockObject $translator;

    /**
     * @var MockObject&EntityManager
     */
    private MockObject $entityManager;

    /**
     * @var MockObject&StageModel
     */
    private MockObject $stageModel;

    /**
     * @var MockObject&FormBuilderInterface
     */
    private MockObject $formBuilder;

    private EmailType $form;

    /**
     * @var CoreParametersHelper&MockObject
     */
    private MockObject $coreParametersHelper;

    /**
     * @var CorePermissions&MockObject
     */
    private MockObject $corePermissions;

    /**
     * @var EmailConfigInterface&MockObject
     */
    private MockObject $emailConfig;

    /**
     * @var ThemeHelperInterface&MockObject
     */
    private MockObject $themeHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator           = $this->createMock(TranslatorInterface::class);
        $this->entityManager        = $this->createMock(EntityManager::class);
        $this->stageModel           = $this->createMock(StageModel::class);
        $this->formBuilder          = $this->createMock(FormBuilderInterface::class);
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $this->corePermissions      = $this->createMock(CorePermissions::class);
        $this->themeHelper          = $this->createMock(ThemeHelperInterface::class);
        $this->emailConfig          = $this->createMock(EmailConfigInterface::class);
        $this->form                 = new EmailType(
            $this->translator,
            $this->entityManager,
            $this->stageModel,
            $this->coreParametersHelper,
            $this->themeHelper,
            $this->corePermissions,
            $this->emailConfig
        );

        $this->formBuilder->method('create')->willReturnSelf();
        $this->formBuilder->method('add')->willReturnSelf();
        $this->formBuilder->method('addModelTransformer')->willReturnSelf();
        $this->corePermissions->method('hasPublishAccessForEntity')->willReturn(true);
        $this->translator->method('trans')->willReturn('translated');
        $this->emailConfig->method('isDraftEnabled')->willReturn(false);
    }

    public function testBuildForm(): void
    {
        $options = ['data' => new Email()];
        $names   = [];
        $this->themeHelper
            ->expects($this->once())
            ->method('getCurrentTheme')
            ->with('blank', 'email')
            ->willReturn('blank');

        $this->formBuilder->method('add')
            ->with(
                $this->callback(
                    function ($name) use (&$names) {
                        $names[] = $name;

                        return true;
                    }
                )
            );

        $this->form->buildForm($this->formBuilder, $options);

        Assert::assertContains('buttons', $names);
    }

    public function testBuildFormHydratesPreferenceCenterAndUtmTagsDefaultsForNewEmail(): void
    {
        $email          = new Email();
        $preferencePage = new Page();

        $this->themeHelper
            ->expects($this->once())
            ->method('getCurrentTheme')
            ->with('blank', 'email')
            ->willReturn('blank');

        $this->coreParametersHelper
            ->method('get')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    'email_default_preference_center_id' => 42,
                    'email_default_utm_source'           => 'newsletter',
                    'email_default_utm_medium'           => 'email',
                    'email_default_utm_campaign'         => 'spring-campaign',
                    'email_default_utm_content'          => 'hero-cta',
                    default                              => false,
                };
            });

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Page::class, 42)
            ->willReturn($preferencePage);

        $this->form->buildForm($this->formBuilder, ['data' => $email]);

        Assert::assertSame($preferencePage, $email->getPreferenceCenter());
        Assert::assertSame(
            [
                'utmSource'   => 'newsletter',
                'utmMedium'   => 'email',
                'utmCampaign' => 'spring-campaign',
                'utmContent'  => 'hero-cta',
            ],
            $email->getUtmTags()
        );
    }

    public function testBuildFormDoesNotOverwriteExistingPreferenceCenterAndUtmTags(): void
    {
        $email                = new Email();
        $existingPage         = new Page();
        $existingUtmTags      = [
            'utmSource'   => 'manual-source',
            'utmMedium'   => 'manual-medium',
            'utmCampaign' => 'manual-campaign',
            'utmContent'  => 'manual-content',
        ];

        $email->setId(1);
        $email->setPreferenceCenter($existingPage);
        $email->setUtmTags($existingUtmTags);

        $this->themeHelper
            ->expects($this->once())
            ->method('getCurrentTheme')
            ->with('blank', 'email')
            ->willReturn('blank');

        $this->coreParametersHelper
            ->method('get')
            ->willReturnMap([
                ['mailer_is_owner', false],
            ]);

        $this->entityManager
            ->expects($this->never())
            ->method('find');

        $this->form->buildForm($this->formBuilder, ['data' => $email]);

        Assert::assertSame($existingPage, $email->getPreferenceCenter());
        Assert::assertSame($existingUtmTags, $email->getUtmTags());
    }

    public function testBuildFormDoesNotOverwriteCloneValuesForNewEmailWithPreFilledFields(): void
    {
        $email            = new Email();
        $existingPage     = new Page();
        $cloneUtmTags     = [
            'utmSource'   => 'clone-source',
            'utmMedium'   => 'clone-medium',
            'utmCampaign' => 'clone-campaign',
            'utmContent'  => 'clone-content',
        ];

        $email->setPreferenceCenter($existingPage);
        $email->setUtmTags($cloneUtmTags);

        $this->themeHelper
            ->expects($this->once())
            ->method('getCurrentTheme')
            ->with('blank', 'email')
            ->willReturn('blank');

        $this->coreParametersHelper
            ->method('get')
            ->willReturnMap([
                ['email_default_preference_center_id', 42],
                ['email_default_utm_source', 'config-source'],
                ['email_default_utm_medium', 'config-medium'],
                ['email_default_utm_campaign', 'config-campaign'],
                ['email_default_utm_content', 'config-content'],
                ['mailer_is_owner', false],
            ]);

        $this->entityManager
            ->expects($this->never())
            ->method('find');

        $this->form->buildForm($this->formBuilder, ['data' => $email]);

        Assert::assertSame($existingPage, $email->getPreferenceCenter());
        Assert::assertSame($cloneUtmTags, $email->getUtmTags());
    }

    public function testBuildFormLeavesFieldsUnchangedWhenDefaultConfigurationIsEmpty(): void
    {
        $email = new Email();

        $this->themeHelper
            ->expects($this->once())
            ->method('getCurrentTheme')
            ->with('blank', 'email')
            ->willReturn('blank');

        $this->coreParametersHelper
            ->method('get')
            ->willReturnMap([
                ['email_default_preference_center_id', null],
                ['email_default_utm_source', ''],
                ['email_default_utm_medium', null],
                ['email_default_utm_campaign', ''],
                ['email_default_utm_content', null],
                ['mailer_is_owner', false],
            ]);

        $this->entityManager
            ->expects($this->never())
            ->method('find');

        $this->form->buildForm($this->formBuilder, ['data' => $email]);

        Assert::assertNull($email->getPreferenceCenter());
        Assert::assertSame([], $email->getUtmTags());
    }

    public function testBuildFormDoesNotApplyConfigDefaultsToCloneWithBlankFields(): void
    {
        // Source email has no preference center and no UTM tags — intentionally blank.
        $source = new Email();
        // Simulate the clone operation performed by EmailController::cloneAction.
        $clone = clone $source;

        Assert::assertTrue($clone->isNew());
        Assert::assertTrue($clone->getIsClone());

        $this->themeHelper
            ->expects($this->once())
            ->method('getCurrentTheme')
            ->with('blank', 'email')
            ->willReturn('blank');

        $this->coreParametersHelper
            ->method('get')
            ->willReturnMap([
                ['email_default_preference_center_id', 42],
                ['email_default_utm_source', 'config-source'],
                ['email_default_utm_medium', 'config-medium'],
                ['email_default_utm_campaign', 'config-campaign'],
                ['email_default_utm_content', 'config-content'],
                ['mailer_is_owner', false],
            ]);

        $this->entityManager
            ->expects($this->never())
            ->method('find');

        $this->form->buildForm($this->formBuilder, ['data' => $clone]);

        Assert::assertNull($clone->getPreferenceCenter(), 'Clone with blank preferenceCenter must not inherit config default');
        Assert::assertSame([], $clone->getUtmTags(), 'Clone with blank utmTags must not inherit config defaults');
    }
}
