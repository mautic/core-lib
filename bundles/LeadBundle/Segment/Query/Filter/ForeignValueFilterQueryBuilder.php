<?php
/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Segment\Query\Filter;

use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;

/**
 * Class ForeignValueFilterQueryBuilder.
 */
class ForeignValueFilterQueryBuilder extends BaseFilterQueryBuilder
{
    /** {@inheritdoc} */
    public static function getServiceId()
    {
        return 'mautic.lead.query.builder.foreign.value';
    }

    /** {@inheritdoc} */
    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter)
    {
        $filterOperator = $filter->getOperator();

        $filterParameters = $filter->getParameterValue();

        if (is_array($filterParameters)) {
            $parameters = [];
            foreach ($filterParameters as $filterParameter) {
                $parameters[] = $this->generateRandomParameterName();
            }
        } else {
            $parameters = $this->generateRandomParameterName();
        }

        $filterParametersHolder = $filter->getParameterHolder($parameters);

        switch ($filterOperator) {
            case 'notIn':
                $tableAlias = $this->generateRandomParameterName();

                if (!is_null($filter->getWhere())) {
                    $where = ' AND '.str_replace(str_replace(MAUTIC_TABLE_PREFIX, '', $filter->getTable()).'.', $tableAlias.'.', $filter->getWhere());
                } else {
                    $where = '';
                }

                $queryBuilder = $queryBuilder->leftJoin(
                    $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'),
                    $filter->getTable(),
                    $tableAlias,
                    $tableAlias.'.lead_id = l.id'.$where
                );

                $expression = $queryBuilder->expr()->in(
                    $tableAlias.'.'.$filter->getField(),
                    $filterParametersHolder
                );

                $queryBuilder->setParametersPairs($filterParametersHolder, $filter->getParameterValue());

                $queryBuilder->addJoinCondition($tableAlias, ' ('.$expression.')');

                break;
            case 'neq':
            case 'notLike':
                $tableAlias   = $this->generateRandomParameterName();
                $queryBuilder->leftJoin(
                    $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'),
                    $filter->getTable(),
                    $tableAlias,
                    $tableAlias.'.lead_id = l.id'
                );

                $queryBuilder->addLogic($queryBuilder->expr()->isNull($tableAlias.'.lead_id'), $filter->getGlue());
                break;
            case 'empty':
            case 'notEmpty':
                $tableAlias = $this->generateRandomParameterName();

                $queryBuilder->leftJoin(
                    $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'),
                    $filter->getTable(),
                    $tableAlias,
                    $tableAlias.'.lead_id = l.id'
                );

                if ($filterOperator == 'empty') {
                    $queryBuilder->addJoinCondition($tableAlias, $queryBuilder->expr()->isNull($tableAlias.'.'.$filter->getField()),
                        $filter->getGlue());
                } else {
                    $queryBuilder->addJoinCondition($tableAlias, $queryBuilder->expr()->isNotNull($tableAlias.'.'.$filter->getField()),
                        $filter->getGlue());
                }

                break;
            default:
                $tableAlias = $this->generateRandomParameterName();

                if (!is_null($filter->getWhere())) {
                    $where = ' AND '.str_replace(str_replace(MAUTIC_TABLE_PREFIX, '', $filter->getTable()).'.', $tableAlias.'.', $filter->getWhere());
                } else {
                    $where = '';
                }

                $queryBuilder->leftJoin(
                    $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'),
                    $filter->getTable(),
                    $tableAlias,
                    $tableAlias.'.lead_id = l.id'.$where
                );

                $queryBuilder->addLogic($queryBuilder->expr()->isNotNull($tableAlias.'.lead_id'), $filter->getGlue());
        }

        switch ($filterOperator) {
            case 'empty':
            case 'notEmpty':
                $expression = $queryBuilder->expr()->isNotNull($tableAlias.'.lead_id');
                $queryBuilder->addLogic($expression, 'and');
                break;
            case 'notIn':
                $expression = $queryBuilder->expr()->isNull($tableAlias.'.lead_id');
                $queryBuilder->addLogic($expression, 'and');
                break;
            case 'neq':
                $expression = $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq($tableAlias.'.'.$filter->getField(), $filterParametersHolder),
                    $queryBuilder->expr()->isNull($tableAlias.'.'.$filter->getField())
                );

                $queryBuilder->addJoinCondition($tableAlias, $expression);
                $queryBuilder->setParametersPairs($parameters, $filterParameters);
                break;
            case 'notLike':
                $expression = $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull($tableAlias.'.'.$filter->getField()),
                    $queryBuilder->expr()->like($tableAlias.'.'.$filter->getField(), $filterParametersHolder)
                );

                $queryBuilder->addJoinCondition($tableAlias, ' ('.$expression.')');
                $queryBuilder->setParametersPairs($parameters, $filterParameters);
                break;
            default:
                $expression = $queryBuilder->expr()->$filterOperator(
                    $tableAlias.'.'.$filter->getField(),
                    $filterParametersHolder
                );
                $queryBuilder->addJoinCondition($tableAlias, ' ('.$expression.')');
                $queryBuilder->setParametersPairs($parameters, $filterParameters);
        }

        return $queryBuilder;
    }
}