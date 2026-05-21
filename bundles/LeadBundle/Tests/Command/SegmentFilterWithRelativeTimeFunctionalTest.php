<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Command;

use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Entity\ListLead;

final class SegmentFilterWithRelativeTimeFunctionalTest extends MauticMysqlTestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('getRelativeHours')]
    public function testSegmentFilterWithRelativeTime(int $hours): void
    {
        $this->saveContacts();
        $segment = $this->saveSegment($hours);

        $this->testSymfonyCommand('mautic:segments:update', ['-i' => $segment->getId()]);
        self::assertCount($hours, $this->em->getRepository(ListLead::class)->findBy(['list' => $segment->getId()]));
    }

    /**
     * @return Lead[]
     */
    private function saveContacts(): array
    {
        // Add 10 contacts
        /** @var LeadRepository $contactRepo */
        $contactRepo = $this->em->getRepository(Lead::class);
        /** @var Lead[] $contacts */
        $contacts    = [];

        for ($i = 1; $i <= 10; ++$i) {
            $contact = new Lead();
            $contact->setFirstname('fn'.$i);
            $contact->setLastname('ln'.$i);
            $contact->setLastActive(new \DateTime(sprintf('-%s hours +15 seconds', $i)));
            $contacts[] = $contact;
        }

        $contactRepo->saveEntities($contacts);

        return $contacts;
    }

    private function saveSegment(int $hours): LeadList
    {
        /** @var LeadListRepository $segmentRepo */
        $segmentRepo = $this->em->getRepository(LeadList::class);
        $segment     = new LeadList();
        $filters     = [
            [
                'object'     => 'lead',
                'glue'       => 'and',
                'field'      => 'last_active',
                'type'       => 'datetime',
                'operator'   => 'gte',
                'properties' => ['filter' => sprintf('-%s hours', $hours)],
            ],
        ];

        $segment->setName('Segment')
            ->setPublicName('Segment')
            ->setFilters($filters)
            ->setAlias('segment');
        $segmentRepo->saveEntity($segment);

        return $segment;
    }

    /**
     * Reproduces a timezone bug where the segment filter for relative hours generates
     * a cutoff timestamp in the local (non-UTC) timezone via toLocalString(), but the
     * `last_active` column is stored in UTC. When Mautic is configured with a non-UTC
     * timezone (e.g. Europe/Prague, UTC+2), toLocalString() shifts the cutoff forward
     * by the UTC offset and contacts fall outside the window even though they should match.
     *
     * Steps:
     *   1. Save a contact whose last_active is 30 minutes ago in UTC.
     *   2. Simulate the configured Mautic timezone being Europe/Prague (UTC+2) by directly
     *      overwriting DateTimeHelper::$defaultLocalTimezone via reflection.
     *   3. Run mautic:segments:update with a "-1 hours" filter.
     *   4. The contact SHOULD be found (30 min < 1 hour), but the bug causes the filter
     *      to compare the UTC DB value against a Prague-local timestamp (+2 h), so it is
     *      missed and the assertion fails.
     */
    public function testSegmentFilterWithRelativeTimeAndNonUtcTimezone(): void
    {
        // Save a contact last active 30 minutes ago – stored as UTC in the DB.
        /** @var LeadRepository $contactRepo */
        $contactRepo = $this->em->getRepository(Lead::class);
        $contact     = new Lead();
        $contact->setFirstname('timezone');
        $contact->setLastname('test');
        $contact->setLastActive(new \DateTime('-30 minutes', new \DateTimeZone('UTC')));
        $contactRepo->saveEntity($contact);

        $segment = $this->saveSegment(1); // filter: last_active >= -1 hour

        // DateTimeHelper caches the configured timezone in a static property.
        // Overwrite it with Europe/Prague (UTC+2) to simulate a non-UTC Mautic install.
        // toLocalString() will then convert the UTC filter cutoff to Prague local time,
        // making the generated filter value 2 hours too late and missing the contact.
        $tzProperty             = new \ReflectionProperty(DateTimeHelper::class, 'defaultLocalTimezone');
        $originalCachedTimezone = $tzProperty->getValue();
        $tzProperty->setValue(null, 'Europe/Prague');

        try {
            $this->testSymfonyCommand('mautic:segments:update', ['-i' => $segment->getId()]);

            // The contact was active only 30 minutes ago so it must appear in the -1 hour segment.
            // Without the fix, toLocalString() converts the UTC cutoff to Prague local time
            // (+2 h), making the filter value 2 hours ahead of the DB value - the contact is
            // missed and the assertion fails.
            self::assertCount(
                1,
                $this->em->getRepository(ListLead::class)->findBy(['list' => $segment->getId()]),
                'Contact last active 30 min ago must be included in the "-1 hour" segment even when the system timezone is non-UTC.'
            );
        } finally {
            $tzProperty->setValue(null, $originalCachedTimezone);
        }
    }

    /**
     * @return iterable<int[]>
     */
    public static function getRelativeHours(): iterable
    {
        yield [1];
        yield [3];
        yield [5];
    }
}
