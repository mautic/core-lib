<?php

namespace Mautic\LeadBundle\Field;

use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\LeadBundle\Entity\CustomFieldEntityInterface;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Helper\CustomFieldHelper;
use Mautic\LeadBundle\Model\FieldModel;

class CustomFieldFindReplace
{
    public function __construct(
        private FieldModel $fieldModel,
    ) {
    }

    /**
     * @return array<string,array<string,string>|string>
     */
    public function getFieldChoices(string $object): array
    {
        return ArrayHelper::flipArray(
            $this->fieldModel->getFieldList(true, true, ['isPublished' => true, 'object' => $object])
        );
    }

    /**
     * @param iterable<object>                                                      $entities
     * @param callable(CustomFieldEntityInterface,array<string,mixed>): void        $setFieldValues
     * @param callable(CustomFieldEntityInterface): bool|null                       $canEdit
     * @param callable(CustomFieldEntityInterface): CustomFieldEntityInterface|null $refreshEntity
     *
     * @return CustomFieldEntityInterface[]
     */
    public function replace(
        string $object,
        string $fieldAlias,
        mixed $findValue,
        mixed $replaceValue,
        iterable $entities,
        callable $setFieldValues,
        ?callable $canEdit = null,
        ?callable $refreshEntity = null,
    ): array {
        $field = $this->getPublishedFieldForObject($fieldAlias, $object);

        if (!$field instanceof LeadField) {
            return [];
        }

        $alias        = $field->getAlias();
        $fieldType    = $field->getType();
        $findValue    = $this->normalizeValue($fieldType, $findValue);
        $replaceValue = $this->normalizeValue($fieldType, $replaceValue);
        $updated      = [];

        if ($findValue === $replaceValue) {
            return [];
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof CustomFieldEntityInterface) {
                continue;
            }

            if ($refreshEntity) {
                $entity = $refreshEntity($entity) ?? $entity;
            }

            if ($canEdit && !$canEdit($entity)) {
                continue;
            }

            if ($findValue !== $this->normalizeValue($fieldType, $entity->getFieldValue($alias))) {
                continue;
            }

            $setFieldValues($entity, [$alias => $replaceValue]);
            $updated[] = $entity;
        }

        return $updated;
    }

    private function getPublishedFieldForObject(string $alias, string $object): ?LeadField
    {
        $field = $this->fieldModel->getRepository()->findOneBy([
            'alias'  => $alias,
            'object' => $object,
        ]);

        if (!$field instanceof LeadField || !$field->isPublished()) {
            return null;
        }

        return $field;
    }

    private function normalizeValue(string $fieldType, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ('' === $value) {
            return null;
        }

        if (is_array($value)) {
            $value = implode('|', $value);
        }

        return CustomFieldHelper::fixValueType($fieldType, $value);
    }
}
