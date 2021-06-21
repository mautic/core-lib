<?php

namespace Mautic\LeadBundle\Entity;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<LeadDevice>
 */
class LeadDeviceRepository extends CommonRepository
{
    /**
     * {@inhertidoc}.
     *
     * @return Paginator
     */
    public function getEntities(array $args = [])
    {
        $q = $this
            ->createQueryBuilder($this->getTableAlias())
            ->select($this->getTableAlias());
        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    public function getTableAlias(): string
    {
        return 'd';
    }

    /**
     * @return array
     */
    public function getDevice($lead, $deviceNames = null, $deviceBrands = null, $deviceModels = null, $deviceOss = null, $deviceId = null)
    {
        $selectQuery = $this->_em->getConnection()->createQueryBuilder();
        $selectQuery->select('es.id as id, es.device as device')
            ->from(MAUTIC_TABLE_PREFIX.'lead_devices', 'es');

        if (null !== $deviceNames) {
            if (!is_array($deviceNames)) {
                $deviceNames = [$deviceNames];
            }

            $orX = $selectQuery->expr()->orX();
            foreach ($deviceNames as $key => $deviceName) {
                $orX->add($selectQuery->expr()->eq('es.device', ':device'.$key));
                $selectQuery->setParameter('device'.$key, $deviceName);
            }
            $selectQuery->andWhere($orX);
        }

        if (null !== $deviceBrands) {
            if (!is_array($deviceBrands)) {
                $deviceBrands = [$deviceBrands];
            }

            $orX = $selectQuery->expr()->orX();
            foreach ($deviceBrands as $key => $deviceBrand) {
                $orX->add($selectQuery->expr()->eq('es.device_brand', ':deviceBrand'.$key));
                $selectQuery->setParameter('deviceBrand'.$key, $deviceBrand);
            }
            $selectQuery->andWhere($orX);
        }

        if (null !== $deviceModels) {
            if (!is_array($deviceModels)) {
                $deviceModels = [$deviceModels];
            }

            $orX = $selectQuery->expr()->orX();
            foreach ($deviceModels as $key => $deviceModel) {
                $orX->add($selectQuery->expr()->eq('es.device_model', ':deviceModel'.$key));
                $selectQuery->setParameter('deviceModel'.$key, $deviceModel);
            }
            $selectQuery->andWhere($orX);
        }

        if (null !== $deviceOss) {
            if (!is_array($deviceOss)) {
                $deviceOss = [$deviceOss];
            }

            $orX = $selectQuery->expr()->orX();
            foreach ($deviceOss as $key => $deviceOs) {
                $orX->add($selectQuery->expr()->eq('es.device_os_name', ':deviceOs'.$key));
                $selectQuery->setParameter('deviceOs'.$key, $deviceOs);
            }
            $selectQuery->andWhere($orX);
        }

        if (null !== $deviceId) {
            $selectQuery->andWhere(
                $selectQuery->expr()->eq('es.id', $deviceId)
            );
        } elseif (null !== $lead) {
            $selectQuery->andWhere(
                $selectQuery->expr()->eq('es.lead_id', $lead->getId())
            );
        }

        // get totals
        $device = $selectQuery->executeQuery()->fetchAllAssociative();

        return (!empty($device)) ? $device[0] : [];
    }

    /**
     * @param string $trackingId
     *
     * @return LeadDevice|null
     */
    public function getByTrackingId($trackingId)
    {
        /** @var LeadDevice $leadDevice */
        $leadDevice = $this->findOneBy([
            'trackingId' => $trackingId,
        ]);

        return $leadDevice;
    }

    /**
     * Check if there is at least one device with filled tracking code assigned to Lead.
     */
    public function isAnyLeadDeviceTracked(Lead $lead): bool
    {
        $alias = $this->getTableAlias();
        $qb    = $this->createQueryBuilder($alias);
        $qb->where(
            $qb->expr()->andX(
                $qb->expr()->eq($alias.'.lead', ':lead'),
                $qb->expr()->isNotNull($alias.'.trackingId')
            )
        )
            ->setParameter('lead', $lead);

        $devices = $qb->getQuery()->getResult();

        return !empty($devices);
    }

    public function getLeadDevices(Lead $lead): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();

        return $qb->select('*')
            ->from(MAUTIC_TABLE_PREFIX.'lead_devices', 'es')
            ->where('lead_id = :leadId')
            ->setParameter('leadId', (int) $lead->getId())
            ->orderBy('date_added', 'desc')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Updates lead ID (e.g. after a lead merge).
     */
    public function updateLead($fromLeadId, $toLeadId): void
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $q->update(MAUTIC_TABLE_PREFIX.'lead_devices')
            ->set('lead_id', (int) $toLeadId)
            ->where('lead_id = '.(int) $fromLeadId)
            ->executeStatement();
    }
}

