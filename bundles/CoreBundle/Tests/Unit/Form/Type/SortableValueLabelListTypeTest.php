<?php

namespace Mautic\CoreBundle\Tests\Unit\Form\Type;

use Mautic\CoreBundle\Form\Type\SortableValueLabelListType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class SortableValueLabelListTypeTest extends TestCase
{
    public function testBuildFormMakesValueOptional(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $type = new SortableValueLabelListType();

        $builder->expects($this->exactly(2))
            ->method('add')
            ->withConsecutive(
                [
                    'label',
                    TextType::class,
                    [
                        'label'          => 'mautic.core.label',
                        'error_bubbling' => true,
                        'attr'           => ['class' => 'form-control'],
                    ]
                ],
                [
                    'value',
                    TextType::class,
                    [
                        'label'          => 'mautic.core.value',
                        'error_bubbling' => true,
                        'required'       => false,
                        'attr'           => ['class' => 'form-control'],
                    ]
                ]
            );

        $builder->expects($this->once())
            ->method('addEventListener')
            ->with(FormEvents::PRE_SUBMIT, $this->isType('callable'));

        $type->buildForm($builder, []);
    }

    /**
     * @dataProvider slugifyDataProvider
     */
    public function testSlugifyMethod(string $input, string $expected): void
    {
        $type = new SortableValueLabelListType();
        $reflection = new \ReflectionClass($type);
        $method = $reflection->getMethod('slugify');
        $method->setAccessible(true);

        $result = $method->invoke($type, $input);
        $this->assertEquals($expected, $result);
    }

    public function slugifyDataProvider(): array
    {
        return [
            ['My Option', 'my_option'],
            ['First Choice!', 'first_choice'],
            ['Test-Value_123', 'test_value_123'],
            ['Special@#$%Characters', 'specialcharacters'],
            ['  Trimmed  Spaces  ', 'trimmed_spaces'],
            ['Multiple___Underscores', 'multiple_underscores'],
            ['Àccénted Chàracters', 'accented_characters'],
            ['', ''],
            ['123Numbers', '123numbers'],
        ];
    }
}
