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

        $call = 0;
        $builder->expects($this->exactly(2))
            ->method('add')
            ->with($this->callback(function ($name) {
                $expected = [
                    ['label', 'value'],
                ];
                return in_array($name, $expected[0], true);
            }),
            $this->callback(function ($type) {
                return $type === TextType::class;
            }),
            $this->callback(function ($options) use (&$call) {
                $expectedOptions = [
                    [
                        'label'          => 'mautic.core.label',
                        'error_bubbling' => true,
                        'attr'           => ['class' => 'form-control'],
                    ],
                    [
                        'label'          => 'mautic.core.value',
                        'error_bubbling' => true,
                        'required'       => false,
                        'attr'           => ['class' => 'form-control'],
                    ],
                ];
                $result = $options === $expectedOptions[$call];
                $call++;
                return $result;
            })
        );

        $builder->expects($this->once())
            ->method('addEventListener')
            ->with(FormEvents::PRE_SUBMIT, $this->isType('callable'));

        $type->buildForm($builder, []);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('slugifyDataProvider')]
    public function testSlugifyMethod(string $input, string $expected): void
    {
        $type = new SortableValueLabelListType();
        $reflection = new \ReflectionClass($type);
        $method = $reflection->getMethod('slugify');
        $method->setAccessible(true);

        $result = $method->invoke($type, $input);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array<int, array{string, string}>
     */
    public static function slugifyDataProvider(): array
    {
        /** @var array<int, array{string, string}> $data */
        $data = [
            ['My Option', 'my_option'],
            ['First Choice!', 'first_choice'],
            ['Test-Value_123', 'test_value_123'],
            ['Special@#$%Characters', 'special_characters'],
            ['  Trimmed  Spaces  ', 'trimmed_spaces'],
            ['Multiple___Underscores', 'multiple_underscores'],
            ['Àccénted Chàracters', 'accented_characters'],
            ['', ''],
            ['123Numbers', '123numbers'],
        ];
        return $data;
    }
}
