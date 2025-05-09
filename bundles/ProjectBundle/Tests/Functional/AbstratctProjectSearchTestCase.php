<?php

declare(strict_types=1);

namespace Mautic\ProjectBundle\Tests\Functional;

use Doctrine\DBAL\Logging\DebugStack;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\ProjectBundle\Entity\Project;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

/**
 * This class should simplify writing functional tests for project search functionality on various entities.
 */
abstract class AbstratctProjectSearchTestCase extends MauticMysqlTestCase
{
    private DebugStack $sqlLogger;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable SQL query logging
        $this->sqlLogger = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($this->sqlLogger);
    }

    /**
     * Output executed SQL queries when a test fails.
     */
    protected function onNotSuccessfulTest(\Throwable $t): void
    {
        if (!empty($this->sqlLogger->queries)) {
            $queries = $this->formatQueriesForOutput($this->sqlLogger->queries);
            echo "\n\nExecuted SQL Queries:\n".$queries."\n";
        }

        parent::onNotSuccessfulTest($t);
    }

    /**
     * @dataProvider searchDataProvider
     *
     * @param string[] $expectedSegments
     * @param string[] $unexpectedSegments
     */
    abstract public function testProjectSearch(string $searchTerm, array $expectedSegments, array $unexpectedSegments): void;

    /**
     * @return \Generator<string, array{searchTerm: string, expectedSegments: array<string>, unexpectedSegments: array<string>}>
     */
    abstract public function searchDataProvider(): \Generator;

    /**
     * Test and assert API as well as UI.
     *
     * @param string[] $expectedSegments
     * @param string[] $unexpectedSegments
     */
    protected function searchAndAssert(string $searchTerm, array $expectedSegments, array $unexpectedSegments): void
    {
        foreach (['api', 's'] as $route) {
            $this->client->request(Request::METHOD_GET, "/$route/segments?search=".urlencode($searchTerm));
            $this->assertResponseIsSuccessful();

            foreach ($expectedSegments as $expectedSegment) {
                Assert::assertStringContainsString($expectedSegment, $this->client->getResponse()->getContent());
            }

            foreach ($unexpectedSegments as $unexpectedSegment) {
                Assert::assertStringNotContainsString($unexpectedSegment, $this->client->getResponse()->getContent());
            }
        }
    }

    protected function createProject(string $name): Project
    {
        $project = new Project();
        $project->setName($name);
        $this->em->persist($project);

        return $project;
    }

    /**
     * Format the queries for readable output.
     *
     * @param array<array<string,mixed>> $queries
     */
    private function formatQueriesForOutput(array $queries): string
    {
        $output = '';
        foreach ($queries as $i => $query) {
            $output .= "\n".($i + 1).'. ['.sprintf('%.2f', $query['executionMS'] * 1000).' ms] ';
            $output .= $query['sql']."\n";

            if (!empty($query['params'])) {
                $output .= '   Parameters: '.json_encode($query['params'])."\n";
            }
        }

        return $output;
    }
}
