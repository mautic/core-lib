<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Tests\Twig;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Field;

final class FieldTemplateTest extends MauticMysqlTestCase
{
    private const FIELD_LABEL         = 'Test Field';
    private const TEXT_FIELD_TEMPLATE = '@MauticForm/Field/text.html.twig';

    public function testFieldTemplateRendersWithCssClasses(): void
    {
        $html = $this->renderTextField($this->createField('50%'));

        $this->assertStringContainsString('mauticform-50', $html);
        $this->assertStringNotContainsString('style="width: 50%"', $html);
    }

    public function testFieldTemplateRendersFullWidthByDefault(): void
    {
        $html = $this->renderTextField($this->createField());

        $this->assertStringContainsString('mauticform-100', $html);
    }

    public function testFieldTemplateMapsAllWidthValuesCorrectly(): void
    {
        $widthMappings = [
            '100%'   => 'mauticform-100',
            '75%'    => 'mauticform-75',
            '66.66%' => 'mauticform-66',
            '50%'    => 'mauticform-50',
            '33.33%' => 'mauticform-33',
            '25%'    => 'mauticform-25',
        ];

        foreach ($widthMappings as $percentage => $expectedClass) {
            $html = $this->renderTextField($this->createField($percentage));

            $this->assertStringContainsString($expectedClass, $html, "Field width {$percentage} should map to class {$expectedClass}");
            $this->assertStringNotContainsString("style=\"width: {$percentage}\"", $html, "Field width {$percentage} should not have inline style");
        }
    }

    private function createField(?string $fieldWidth = null): Field
    {
        $field = new Field();
        $field->setType('text');
        $field->setLabel(self::FIELD_LABEL);
        $field->setAlias('test_field');
        $field->setOrder(1);

        if (null !== $fieldWidth) {
            $field->setFieldWidth($fieldWidth);
        }

        return $field;
    }

    private function renderTextField(Field $field): string
    {
        $twig     = $this->getContainer()->get('twig');
        $template = $twig->load(self::TEXT_FIELD_TEMPLATE);

        return $template->render([
            'field'          => $field,
            'fields'         => [],
            'id'             => 'test_id',
            'formName'       => 'test_form',
            'containerClass' => 'text',
            'type'           => 'text',
            'inputClass'     => 'input',
        ]);
    }
}
