<?php

declare(strict_types=1);

namespace Mautic\ReportBundle\Tests\Form\Type;

use Mautic\CoreBundle\Form\Type\ButtonGroupType;
use Mautic\ReportBundle\Entity\Report;
use Mautic\ReportBundle\Form\Type\DynamicFiltersType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DynamicFiltersTypeTest extends TestCase
{
    private MockObject $formBuilder;
    private MockObject $translator;
    private MockObject $report;
    private \stdClass $filterDefinitions;
    private DynamicFiltersType $dynamicFiltersType;

    protected function setUp(): void
    {
        $this->formBuilder        = $this->createMock(FormBuilderInterface::class);
        $this->translator         = $this->createMock(TranslatorInterface::class);
        $this->report             = $this->createMock(Report::class);
        $this->filterDefinitions  = new \stdClass();
        $this->dynamicFiltersType = new DynamicFiltersType($this->translator);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createFilter(array $overrides = []): array
    {
        return array_merge([
            'dynamic'   => 1,
            'column'    => 'test_column',
            'condition' => 'eq',
            'value'     => 'test_value',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createFilterDefinition(array $overrides = []): array
    {
        return array_merge([
            'alias'         => 'test_alias',
            'label'         => 'Test Label',
            'type'          => 'text',
            'operatorGroup' => 'text',
            'operators'     => ['eq' => 'mautic.core.operator.equals'],
        ], $overrides);
    }

    private function setupBasicReport(): void
    {
        $this->report->method('getId')->willReturn(1);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function setupFilterDefinitions(array $definition): void
    {
        $this->filterDefinitions->definitions = [
            'test_column' => $definition,
        ];
    }

    private function setupTranslator(string $translation = 'Equals'): void
    {
        $this->translator->method('trans')->willReturn($translation);
    }

    /**
     * @return array<string, mixed>
     */
    private function getBasicOptions(): array
    {
        return [
            'report'            => $this->report,
            'filterDefinitions' => $this->filterDefinitions,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function getOptionsWithData(array $data): array
    {
        return array_merge($this->getBasicOptions(), ['data' => $data]);
    }

    public function testBuildFormWithNoDynamicFilters(): void
    {
        $this->report->method('getFilters')->willReturn([]);

        $this->formBuilder->expects($this->never())->method('add');

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithNonDynamicFilter(): void
    {
        $this->report->method('getFilters')->willReturn([
            ['dynamic' => 0, 'column' => 'test_column'],
        ]);

        $this->formBuilder->expects($this->never())->method('add');

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterDefaultType(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter()]);
        $this->setupFilterDefinitions($this->createFilterDefinition());
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                TextType::class,
                $this->callback(function (array $args) {
                    return 'Test Label (Equals)' === $args['label']
                           && 'control-label' === $args['label_attr']['class']
                           && 'form-control' === $args['attr']['class']
                           && "Mautic.filterTableData('report.1','test_column',mQuery(this).val(),'list','.report-content');" === $args['attr']['onchange']
                           && false === $args['required'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterBoolType(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['value' => 1])]);
        $this->setupFilterDefinitions($this->createFilterDefinition(['type' => 'bool', 'operatorGroup' => 'bool']));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                ButtonGroupType::class,
                $this->callback(function (array $args) {
                    return 'Test Label (Equals)' === $args['label']
                           && $args['choices'] === [
                               [
                                   'mautic.core.form.no'      => false,
                                   'mautic.core.form.yes'     => true,
                                   'mautic.core.filter.clear' => '2',
                               ],
                           ]
                           && 1 === $args['data'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterBooleanType(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['value' => 0])]);
        $this->setupFilterDefinitions($this->createFilterDefinition(['type' => 'boolean', 'operatorGroup' => 'bool']));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                ButtonGroupType::class,
                $this->callback(function (array $args) {
                    return 0 === $args['data'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterDateType(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['value' => '2023-01-01'])]);
        $this->setupFilterDefinitions($this->createFilterDefinition(['type' => 'date', 'operatorGroup' => 'date']));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                DateType::class,
                $this->callback(function (array $args) {
                    return 'string' === $args['input']
                           && 'single_text' === $args['widget']
                           && false === $args['html5']
                           && 'y-MM-dd' === $args['format']
                           && false !== strpos($args['attr']['class'], 'datepicker');
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterDateTimeType(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['value' => '2023-01-01 12:00:00'])]);
        $this->setupFilterDefinitions($this->createFilterDefinition(['type' => 'datetime', 'operatorGroup' => 'datetime']));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                DateTimeType::class,
                $this->callback(function (array $args) {
                    return 'string' === $args['input']
                           && 'single_text' === $args['widget']
                           && false === $args['html5']
                           && 'y-MM-dd HH:mm:ss' === $args['format']
                           && false !== strpos($args['attr']['class'], 'datetimepicker');
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterSelectType(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['value' => 'option1'])]);
        $this->setupFilterDefinitions($this->createFilterDefinition([
            'type'          => 'select',
            'operatorGroup' => 'select',
            'list'          => ['option1' => 'Option 1', 'option2' => 'Option 2'],
        ]));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                ChoiceType::class,
                $this->callback(function (array $args) {
                    return $args['choices'] === ['Option 1' => 'option1', 'Option 2' => 'option2'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterMultiselectType(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['condition' => 'in', 'value' => ['option1', 'option2']])]);
        $this->setupFilterDefinitions($this->createFilterDefinition([
            'type'          => 'multiselect',
            'operatorGroup' => 'multiselect',
            'operators'     => ['in' => 'mautic.core.operator.in'],
            'list'          => ['option1' => 'Option 1', 'option2' => 'Option 2'],
        ]));
        $this->setupTranslator('In');

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                ChoiceType::class,
                $this->callback(function (array $args) {
                    return true === $args['multiple']
                           && $args['choices'] === ['Option 1' => 'option1', 'Option 2' => 'option2'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterWithData(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['value' => 0])]);
        $this->setupFilterDefinitions($this->createFilterDefinition(['type' => 'bool', 'operatorGroup' => 'bool']));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                ButtonGroupType::class,
                $this->callback(function (array $args) {
                    return true === $args['data'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getOptionsWithData(['test_alias' => 1]));
    }

    public function testBuildFormWithDynamicFilterInvalidOperatorGroup(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter()]);
        $this->setupFilterDefinitions($this->createFilterDefinition(['operatorGroup' => 'invalid_group']));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                TextType::class,
                $this->callback(function (array $args) {
                    return 'Test Label (Equals)' === $args['label'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterNoOperatorGroup(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter()]);
        $this->setupFilterDefinitions($this->createFilterDefinition(['operatorGroup' => null]));
        $this->setupTranslator();

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                TextType::class,
                $this->callback(function (array $args) {
                    return 'Test Label (Equals)' === $args['label'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testBuildFormWithDynamicFilterNoOperatorLabel(): void
    {
        $this->setupBasicReport();
        $this->report->method('getFilters')->willReturn([$this->createFilter(['condition' => 'invalid_condition'])]);
        $this->setupFilterDefinitions($this->createFilterDefinition());
        $this->setupTranslator('');

        $this->formBuilder->expects($this->once())
            ->method('add')
            ->with(
                'test_alias',
                TextType::class,
                $this->callback(function (array $args) {
                    return 'Test Label' === $args['label'];
                })
            );

        $this->dynamicFiltersType->buildForm($this->formBuilder, $this->getBasicOptions());
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('report_dynamicfilters', $this->dynamicFiltersType->getBlockPrefix());
    }

    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $this->dynamicFiltersType->configureOptions($resolver);

        $options = $resolver->resolve([]);
        $this->assertArrayHasKey('filterDefinitions', $options);
        $this->assertArrayHasKey('report', $options);
        $this->assertInstanceOf(Report::class, $options['report']);
    }
}
