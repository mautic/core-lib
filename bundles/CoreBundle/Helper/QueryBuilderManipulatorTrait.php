<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Helper;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

trait QueryBuilderManipulatorTrait
{
    private function copyParams(QueryBuilder $fromQueryBuilder, QueryBuilder $toQueryBuilder): void
    {
        foreach ($fromQueryBuilder->getParameters() as $key => $value) {
            $paramType = is_array($value) ? ArrayParameterType::STRING : null;
            $toQueryBuilder->setParameter($key, $value, $paramType);
        }
    }
}
