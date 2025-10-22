<?php

declare(strict_types=1);

namespace Mautic\ProjectBundle\DTO;

final readonly class DetailRoute
{
    public function __construct(
        public string $route,
        private string $idParameterName,
        private array $otherParameters = [],
    ) {
    }

    public function getParameters(string $id): array
    {
        return [$this->idParameterName => $id] + $this->otherParameters;
    }
}
