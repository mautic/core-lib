<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\EventListener;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\MaintenanceEvent;
use Mautic\CoreBundle\Factory\MauticFactory;

/**
 * Class MaintenanceSubscriber
 */
class MaintenanceSubscriber extends CommonSubscriber
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * MaintenanceSubscriber constructor.
     *
     * @param MauticFactory $factory
     * @param Connection    $db
     */
    public function __construct(MauticFactory $factory, Connection $db)
    {
        parent::__construct($factory);

        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::MAINTENANCE_CLEANUP_DATA => ['onDataCleanup', -50]
        ];
    }

    /**
     * @param $isDryRun
     * @param $date
     *
     * @return int
     */
    public function onDataCleanup (MaintenanceEvent $event)
    {
        $qb = $this->db->createQueryBuilder()
            ->setParameter('date', $event->getDate()->format('Y-m-d H:i:s'));

        if ($event->isDryRun()) {
            $rows = (int) $qb->select('count(*) as records')
                ->from(MAUTIC_TABLE_PREFIX.'audit_log', 'log')
                ->where(
                    $qb->expr()->gte('log.date_added', ':date')
                )
                ->execute()
                ->fetchColumn();
        } else {
            $rows = (int) $qb->delete(MAUTIC_TABLE_PREFIX.'audit_log')
                ->where(
                    $qb->expr()->lte('date_added', ':date')
                )
                ->execute();
        }

        $event->setStat($this->translator->trans('mautic.maintenance.audit_log'), $rows, $qb->getSQL(), $qb->getParameters());
    }
}
