<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Model;

use Doctrine\ORM\Tools\Pagination\Paginator;

interface GlobalSearchModalInterface
{
    public function getEntitiesForGlobalSearch(string $searchString): ?Paginator;

    public function canViewOwnEntity(): bool;

    public function canViewOthersEntity(): bool;
}
