<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Tests\Form\Type;

use Mautic\FormBundle\Form\Type\FormFieldSliderType;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Test\TypeTestCase;

final class FormFieldSliderTypeTest extends TypeTestCase
{
    /** @var MockObject|FormBuilderInterface */
    private $formBuilder;

    /** @var AbstractType<FormFieldSliderType> */
    private $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formBuilder = $this->createMock(FormBuilderInterface::class);
        $this->form        = new FormFieldSliderType();
    }

    public function testBuildForm(): void
    {
        $options = [
            'data' => [
                'min'  => 0,
                'max'  => 100,
                'step' => 1,
            ],
        ];
        $matcher = $this->exactly(3);

        $this->formBuilder->expects($matcher)
            ->method('add')->willReturnCallback(function (...$parameters) use ($matcher) {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertSame('min', $parameters[0]);
                    $this->assertSame(IntegerType::class, $parameters[1]);
                }
                if (2 === $matcher->numberOfInvocations()) {
                    $this->assertSame('max', $parameters[0]);
                    $this->assertSame(IntegerType::class, $parameters[1]);
                }
                if (3 === $matcher->numberOfInvocations()) {
                    $this->assertSame('step', $parameters[0]);
                    $this->assertSame(IntegerType::class, $parameters[1]);
                }

                return $this->formBuilder;
            });

        $this->form->buildForm($this->formBuilder, $options);
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
}
