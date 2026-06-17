<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Tests\Form;

use Mautic\CoreBundle\Form\ToBcBccFieldsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class ToBcBccFieldsTraitTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testSingleValidEmailPasses(): void
    {
        $form = $this->factory->create(ToBcBccStubFormType::class);
        $form->submit(['to' => 'user@example.com', 'cc' => '', 'bcc' => '']);

        self::assertTrue($form->isValid());
    }

    public function testCommaSeparatedValidEmailsPasses(): void
    {
        $form = $this->factory->create(ToBcBccStubFormType::class);
        $form->submit(['to' => 'user1@example.com,user2@example.com', 'cc' => '', 'bcc' => '']);

        self::assertTrue($form->isValid());
    }

    public function testCommaSeparatedEmailsWithSpacesPasses(): void
    {
        $form = $this->factory->create(ToBcBccStubFormType::class);
        $form->submit(['to' => 'user1@example.com, user2@example.com', 'cc' => '', 'bcc' => '']);

        self::assertTrue($form->isValid());
    }

    public function testEmptyValuePasses(): void
    {
        $form = $this->factory->create(ToBcBccStubFormType::class);
        $form->submit(['to' => '', 'cc' => '', 'bcc' => '']);

        self::assertTrue($form->isValid());
    }

    public function testInvalidEmailFails(): void
    {
        $form = $this->factory->create(ToBcBccStubFormType::class);
        $form->submit(['to' => 'notanemail', 'cc' => '', 'bcc' => '']);

        self::assertFalse($form->isValid());
    }

    public function testOneInvalidEmailInListFails(): void
    {
        $form = $this->factory->create(ToBcBccStubFormType::class);
        $form->submit(['to' => 'valid@example.com,notanemail', 'cc' => '', 'bcc' => '']);

        self::assertFalse($form->isValid());
    }

    public function testCcAndBccFieldsAlsoValidate(): void
    {
        $form = $this->factory->create(ToBcBccStubFormType::class);
        $form->submit(['to' => '', 'cc' => 'invalid', 'bcc' => 'also-invalid']);

        self::assertFalse($form->isValid());
    }
}

/**
 * @extends AbstractType<mixed>
 */
class ToBcBccStubFormType extends AbstractType
{
    use ToBcBccFieldsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addToBcBccFields($builder);
    }
}
