<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Command;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadList;

class SegmentStatCommandTest extends MauticMysqlTestCase
{
    /**
     * @throws \Exception
     */
    public function testSegmentStatCommandWithOutSegment(): void
    {
        $output = $this->runCommand('mautic:segments:stat');

        $this->assertStringContainsString('There is no segment to show!!', $output);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testSegmentStatCommandWithSegment(): void
    {
        $segmentName = 'Segment For Campaign';
        $segment     = new LeadList();
        $segment->setName($segmentName);
        $segment->setAlias(mb_strtolower($segmentName));
        $segment->setIsPublished(true);
        $this->em->persist($segment);
        $this->em->flush();

        $campaign = new Campaign();
        $campaign->setName('Campaign With LeadList');
        $campaign->addList($segment);

        $this->em->persist($campaign);
        $this->em->flush();

        $output = $this->runCommand('mautic:segments:stat');

        //testing segment name
        $this->assertStringContainsString('Segment For Campaign', $output);

        //testing segment is used
        $this->assertStringContainsString('1', $output);
    }
}
