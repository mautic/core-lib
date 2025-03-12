<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Validator;

use Mautic\DynamicContentBundle\DynamicContent\TypeList;
use Mautic\DynamicContentBundle\Model\DynamicContentModel;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class TextOnlyDynamicContentValidator extends ConstraintValidator
{
    public function __construct(private DynamicContentModel $dynamicContentModel)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!is_string($value)) {
            return;
        }

        // Pattern to match DWC tokens in the format {dwc=slotname}
        preg_match_all('/{dwc=([^}]*)}/', $value, $matches);

        foreach ($matches[1] as $slotName) {
            // Retrieve DWC item by slot name
            $dwcItem = $this->dynamicContentModel->checkEntityBySlotName($slotName, TypeList::HTML);

            // Perform the validation against the type
            if ($dwcItem) {
                $this->context->buildViolation(
                    'mautic.email.subject.dynamic_content.text_only', ['%slotName%' => $slotName])
                    ->addViolation();
            }
        }
    }
}
