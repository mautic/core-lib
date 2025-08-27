<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Tests\Form\Type;

use Mautic\FormBundle\Form\Type\FormFieldSliderType;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FormFieldSliderTypeTest extends TypeTestCase
{
    /** @var MockObject|TranslatorInterface */
    private $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([
                FormFieldSliderType::class => new FormFieldSliderType($this->translator),
            ], []),
        ];
    }

    public function testSubmitValidData(): void
    {
        $formData = [
            'min'  => 0,
            'max'  => 50,
            'step' => 5,
        ];
        $form = $this->factory->create(FormFieldSliderType::class);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertNotEmpty($form->getData());

        $view     = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }

    public function testSubmitInvalidData(): void
    {
        $this->translator->expects($this->any())
            ->method('trans')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mautic.form.field.form.slider_max_gt_min_error' => 'Maximum value must be greater than minimum value',
                    'mautic.form.field.form.slider_step_min_error' => 'Step value must be at least 1',
                    'mautic.form.field.form.slider_step_lt_max_error' => 'Step must be less than the maximum value',
                    default => $key,
                };
            });

        $form = $this->factory->create(FormFieldSliderType::class);

        $invalidData = [
            'min'  => 10,
            'max'  => 5,
            'step' => 15,
        ];

        $form->submit($invalidData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $errors = $form->getErrors(true);
        $this->assertGreaterThan(0, count($errors));
    }
}
