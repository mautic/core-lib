<?php

namespace Mautic\CoreBundle\Entity;

use Doctrine\Common\Collections\Collection;

interface VariantEntityInterface
{
    public function getVariantParent(): ?VariantEntityInterface;

    /**
     * @return $this
     */
    public function setVariantParent(?VariantEntityInterface $parent = null): static;

    public function removeVariantParent(): void;

    public function getVariantChildren(): Collection;

    /**
     * @return $this
     */
    public function addVariantChild(VariantEntityInterface $child): static;

    public function removeVariantChild(VariantEntityInterface $child): void;

    public function getVariantSettings(): array;

    public function getVariantStartDate(): ?\DateTimeInterface;

    /**
     * @return array<int, mixed>
     */
    public function getVariants(): array;

    public function isVariant(bool $isChild = false): bool;
}
