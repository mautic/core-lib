<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Tests\Twig;

/**
 * Trait to provide PHPUnit 10 compatibility for Twig integration tests
 * This handles the static data provider requirements and legacy test overrides.
 */
trait TwigIntegrationTestTrait
{
    /**
     * Static data provider for integration tests
     * This uses a helper method to work around PHPUnit 10's static requirements.
     */
    public static function integrationTestDataProvider(): iterable
    {
        return static::getIntegrationTestData();
    }

    /**
     * Get the fixtures directory for the test
     * Uses the directory of the class that uses this trait.
     */
    public static function getFixturesDirectory(): string
    {
        // Get the directory of the class that uses this trait
        $reflection = new \ReflectionClass(static::class);

        return dirname($reflection->getFileName()).'/Fixtures/';
    }

    /**
     * Helper method to get integration test data
     * This creates a temporary instance to call the parent's non-static methods.
     */
    private static function getIntegrationTestData(): iterable
    {
        // Create a temporary instance of the actual test class
        $reflection = new \ReflectionClass(static::class);
        $instance   = $reflection->newInstanceWithoutConstructor();

        // Call the parent's getTests method
        return $instance->getTests('testIntegration', false);
    }

    /**
     * @dataProvider integrationTestDataProvider
     */
    public function testIntegration($file, $message, $condition, $templates, $exception, $outputs, $deprecation = '')
    {
        $this->doIntegrationTest($file, $message, $condition, $templates, $exception, $outputs, $deprecation);
    }

    /**
     * Override the legacy integration test to prevent it from running
     * We don't use legacy Twig features, so this test is not needed.
     */
    public function testLegacyIntegration($file = null, $message = null, $condition = null, $templates = null, $exception = null, $outputs = null, $deprecation = '')
    {
        $this->markTestSkipped('Legacy Twig tests are not applicable to this project');
    }

    /**
     * Override to prevent the legacy data provider deprecation warning
     * We return empty array since we skip the test anyway.
     *
     * @return array
     */
    public function getLegacyTests()
    {
        return [];
    }
}
