<?php

namespace Mautic\SmsBundle\Helper;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Helper\PhoneNumberHelper;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\SmsBundle\Exception\NumberNotFoundException;

class ContactHelper
{
    private \Mautic\LeadBundle\Entity\LeadRepository $leadRepository;

    private \Doctrine\DBAL\Connection $connection;

    private \Mautic\CoreBundle\Helper\PhoneNumberHelper $phoneNumberHelper;

    public function __construct(
        LeadRepository $leadRepository,
        Connection $connection,
        PhoneNumberHelper $phoneNumberHelper
    ) {
        $this->leadRepository    = $leadRepository;
        $this->connection        = $connection;
        $this->phoneNumberHelper = $phoneNumberHelper;
    }

    /**
     * @param string $number
     *
     * @throws NumberNotFoundException
     */
    public function findContactsByNumber($number): ArrayCollection
    {
        // Who knows what the number was originally formatted as so let's try a few
        $searchForNumbers = $this->phoneNumberHelper->getFormattedNumberList($number);

        $qb = $this->connection->createQueryBuilder();

        $foundContacts = $qb->select('l.id')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l')
            ->where(
                $qb->expr()->or(
                    'l.mobile IN (:numbers)',
                    'l.phone IN (:numbers)'
                )
            )
            ->setParameter('numbers', $searchForNumbers, Connection::PARAM_STR_ARRAY)
            ->executeQuery()
            ->fetchAllAssociative();

        $ids = array_column($foundContacts, 'id');
        if (0 === count($ids)) {
            throw new NumberNotFoundException($number);
        }

        $collection = new ArrayCollection();
        /** @var Lead[] $contacts */
        $contacts = $this->leadRepository->getEntities(['ids' => $ids]);
        foreach ($contacts as $contact) {
            $collection->set($contact->getId(), $contact);
        }

        return $collection;
    }
}
