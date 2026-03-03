<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Model\AbTest;

use Mautic\CoreBundle\Entity\VariantEntityInterface;
use Mautic\CoreBundle\Model\AbTest\VariantConverterService;
use Mautic\EmailBundle\Entity\Email;

/**
 * Class EmailVariantConverterService.
 */
class EmailVariantConverterService
{
    /**
     * EmailVariantConverterService constructor.
     */
    public function __construct(private VariantConverterService $variantConverterService)
    {
    }

    public function convertWinnerVariant(Email $email): void
    {
        $this->variantConverterService->convertWinnerVariant($email);
    }

    /**
     * @return array<VariantEntityInterface>
     */
    public function getUpdatedVariants()
    {
        return $this->variantConverterService->getUpdatedVariants();
    }
}
