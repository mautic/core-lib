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
use Mautic\LeadBundle\Segment\Exception\InvalidUseException;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryException;
use Mautic\LeadBundle\Segment\RandomParameterName;

/**
 * Class BaseFilterQueryBuilder.
 */
class BaseFilterQueryBuilder implements FilterQueryBuilderInterface
{
    /** @var RandomParameterName */
    private $parameterNameGenerator;

    /**
     * BaseFilterQueryBuilder constructor.
     *
     * @param RandomParameterName $randomParameterNameService
     */
    public function __construct(RandomParameterName $randomParameterNameService)
    {
        $this->parameterNameGenerator = $randomParameterNameService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getServiceId()
    {
        return 'mautic.lead.query.builder.basic';
    }

    /**
     * {@inheritdoc}
     */
    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter)
    {
        $filterOperator = $filter->getOperator();
        $filterGlue     = $filter->getGlue();
        $filterAggr     = $filter->getAggregateFunction();

        try {
            $filter->getColumn();
        } catch (QueryException $e) {
            // We do ignore not found fields as they may be just removed custom field
            return $queryBuilder;
        }

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

        $tableAlias = $queryBuilder->getTableAlias($filter->getTable());

        // for aggregate function we need to create new alias and not reuse the old one
        if ($filterAggr) {
            $tableAlias = false;
        }

        if (!$tableAlias) {
            $tableAlias = $this->generateRandomParameterName();
            if ($filterAggr) {
                if ($filter->getTable() != MAUTIC_TABLE_PREFIX.'leads') {
                    throw new InvalidUseException('You should use ForeignFuncFilterQueryBuilder instead.');
                }
                $queryBuilder->leftJoin(
                            $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'),
                            $filter->getTable(),
                            $tableAlias,
                            sprintf('%s.id = %s.lead_id', $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'), $tableAlias)
                        );
            } else {
                if ($filter->getTable() == MAUTIC_TABLE_PREFIX.'companies') {
                    $relTable = $this->generateRandomParameterName();
                    $queryBuilder->leftJoin('l', MAUTIC_TABLE_PREFIX.'companies_leads', $relTable, $relTable.'.lead_id = l.id');
                    $queryBuilder->leftJoin($relTable, $filter->getTable(), $tableAlias, $tableAlias.'.id = '.$relTable.'.company_id');
                } else {
                    $queryBuilder->leftJoin(
                                $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'),
                                $filter->getTable(),
                                $tableAlias,
                                sprintf('%s.id = %s.lead_id', $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads'), $tableAlias)
                            );
                }
            }
        }

        switch ($filterOperator) {
            case 'empty':
                $expression = $queryBuilder->expr()->isNull($tableAlias.'.'.$filter->getField());

                break;
            case 'notEmpty':
                $expression = $queryBuilder->expr()->isNotNull($tableAlias.'.'.$filter->getField());
                break;
            case 'neq':
                if ($filter->isColumnTypeBoolean() && $filter->getParameterValue() == 1) {
                    $expression = $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->isNull($tableAlias.'.'.$filter->getField()),
                        $queryBuilder->expr()->$filterOperator(
                            $tableAlias.'.'.$filter->getField(),
                            $filterParametersHolder
                        )
                    );
                    break;  // Break will be performed only if the condition above matches
                }
            case 'startsWith':
            case 'endsWith':
            case 'gt':
            case 'eq':
            case 'gte':
            case 'like':
            case 'notLike':
            case 'lt':
            case 'lte':
            case 'notIn':
            case 'in':
            case 'regexp':
            case 'between':
            case 'notBetween':
            case 'notRegexp':
                    $expression = $queryBuilder->expr()->$filterOperator(
                        $tableAlias.'.'.$filter->getField(),
                        $filterParametersHolder
                    );

                break;
            default:
                throw new \Exception('Dunno how to handle operator "'.$filterOperator.'"');
        }

        if ($queryBuilder->isJoinTable($filter->getTable())) {
            $queryBuilder->addJoinCondition($tableAlias, ' ('.$expression.')');
        }

        $queryBuilder->addLogic($expression, $filterGlue);

        $queryBuilder->setParametersPairs($parameters, $filterParameters);

        return $queryBuilder;
    }

    /**
     * @param RandomParameterName $parameterNameGenerator
     *
     * @return BaseFilterQueryBuilder
     */
    public function setParameterNameGenerator($parameterNameGenerator)
    {
        $this->parameterNameGenerator = $parameterNameGenerator;

        return $this;
    }

    /**
     * @return string
     */
    protected function generateRandomParameterName()
    {
        return $this->parameterNameGenerator->generateRandomParameterName();
    }
}