<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Controller;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\ProjectBundle\Tests\Functional\AbstratctProjectSearchTestCase;

final class LeadListProjectSearchFunctionalTest extends AbstratctProjectSearchTestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('searchDataProvider')]
    public function testProjectSearch(string $searchTerm, array $expectedSegments, array $unexpectedSegments): void
    {
        $projectOne   = $this->createProject('Project One');
        $projectTwo   = $this->createProject('Project Two');
        $projectThree = $this->createProject('Project Three');

        $segmentAlpha = $this->createSegment('Segment Alpha');
        $segmentBeta  = $this->createSegment('Segment Beta');
        $this->createSegment('Segment Gamma');
        $this->createSegment('Segment Delta');

        $segmentAlpha->addProject($projectOne);
        $segmentAlpha->addProject($projectTwo);
        $segmentBeta->addProject($projectTwo);
        $segmentBeta->addProject($projectThree);

        $this->em->flush();
        $this->em->clear();

        $this->searchAndAssert($searchTerm, $expectedSegments, $unexpectedSegments);
    }

    /**
     * @return \Generator<string, array{searchTerm: string, expectedSegments: array<string>, unexpectedSegments: array<string>}>
     */
    public static function searchDataProvider(): \Generator
    {
        yield 'search by one project' => [
            'searchTerm'         => 'project:"Project Two"',
            'expectedSegments'   => ['Segment Alpha', 'Segment Beta'],
            'unexpectedSegments' => ['Segment Gamma', 'Segment Delta'],
        ];

        yield 'search by one project AND segment name' => [
            'searchTerm'         => 'project:"Project Two" AND Beta',
            'expectedSegments'   => ['Segment Beta'],
            'unexpectedSegments' => ['Segment Alpha', 'Segment Gamma', 'Segment Delta'],
        ];

        yield 'search by one project OR segment name' => [
            'searchTerm'         => 'project:"Project Two" OR Gamma',
            'expectedSegments'   => ['Segment Alpha', 'Segment Beta', 'Segment Gamma'],
            'unexpectedSegments' => ['Segment Delta'],
        ];

        yield 'search by NOT one project' => [
            'searchTerm'         => '!project:"Project Two"',
            'expectedSegments'   => ['Segment Gamma', 'Segment Delta'],
            'unexpectedSegments' => ['Segment Alpha', 'Segment Beta'],
        ];

        yield 'search by two projects with AND' => [
            'searchTerm'         => 'project:"Project Two" AND project:"Project Three"',
            'expectedSegments'   => ['Segment Beta'],
            'unexpectedSegments' => ['Segment Alpha', 'Segment Gamma', 'Segment Delta'],
        ];

        yield 'search by two projects with NOT AND' => [
            'searchTerm'         => '!project:"Project Two" AND !project:"Project Three"',
            'expectedSegments'   => ['Segment Gamma', 'Segment Delta'],
            'unexpectedSegments' => ['Segment Alpha', 'Segment Beta'],
        ];

        yield 'search by two projects with OR' => [
            'searchTerm'         => 'project:"Project Two" OR project:"Project Three"',
            'expectedSegments'   => ['Segment Alpha', 'Segment Beta'],
            'unexpectedSegments' => ['Segment Gamma', 'Segment Delta'],
        ];

        yield 'search by two projects with NOT OR' => [
            'searchTerm'         => '!project:"Project Two" OR !project:"Project Three"',
            'expectedSegments'   => ['Segment Alpha', 'Segment Gamma', 'Segment Delta'],
            'unexpectedSegments' => ['Segment Beta'],
        ];
    }

    private function createSegment(string $name): LeadList
    {
        $leadList = new LeadList();
        $leadList->setName($name);
        $leadList->setPublicName($name);
        $leadList->setAlias(str_replace(' ', '-', mb_strtolower($name)));
        $this->em->persist($leadList);

        return $leadList;
    }
}
