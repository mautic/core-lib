<?php

namespace Mautic\CoreBundle\Tests\Unit\Templating\Helper;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Templating\Helper\DateHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class DateHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|TranslatorInterface
     */
    private $translator;

    /**
     * @var DateHelper
     */
    private $helper;

    /**
     * @var string
     */
    private static $oldTimezone;

    /**
     * @var CoreParametersHelper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $coreParametersHelper;

    public static function setUpBeforeClass(): void
    {
        self::$oldTimezone = date_default_timezone_get();
    }

    public static function tearDownAfterClass(): void
    {
        date_default_timezone_set(self::$oldTimezone);
    }

    protected function setUp(): void
    {
        $envParameters     = json_encode(['default_timezone' => 'Etc/GMT-4']);
        putenv('MAUTIC_CONFIG_PARAMETERS='.$envParameters);

        $this->translator           = $this->createMock(TranslatorInterface::class);
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $this->helper               = new DateHelper(
            'F j, Y g:i a T',
            'D, M d',
            'F j, Y',
            'g:i a',
            $this->translator,
            $this->coreParametersHelper
        );
    }

    public function testStringToText()
    {
        $this->setDefaultLocalTimezone('Etc/GMT-4');
        $time = '2016-01-27 14:30:00';
        $this->assertSame('January 27, 2016 6:30 pm', $this->helper->toText($time, 'UTC', 'Y-m-d H:i:s', true));
    }

    public function testStringToTextUtc()
    {
        $this->setDefaultLocalTimezone('UTC');
        $time = '2016-01-27 14:30:00';

        $this->assertSame('January 27, 2016 2:30 pm', $this->helper->toText($time, 'UTC', 'Y-m-d H:i:s', true));
    }

    public function testDateTimeToText()
    {
        $this->setDefaultLocalTimezone('Etc/GMT-4');
        $dateTime = new \DateTime('2016-01-27 14:30:00', new \DateTimeZone('UTC'));
        $this->assertSame('January 27, 2016 6:30 pm', $this->helper->toText($dateTime, 'UTC', 'Y-m-d H:i:s', true));
    }

    public function testDateTimeToTextUtc()
    {
        $this->setDefaultLocalTimezone('UTC');
        $dateTime = new \DateTime('2016-01-27 14:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('January 27, 2016 2:30 pm', $this->helper->toText($dateTime, 'UTC', 'Y-m-d H:i:s', true));
    }

    public function testToTextWithConfigurationToTime()
    {
        $this->coreParametersHelper->method('get')
            ->with('date_format_timeonly')
            ->willReturn('00:00:00');

        $this->translator->method('trans')
            ->willReturnCallback(
                function (string $key, array $parameters = []) {
                    if (isset($parameters['%time%'])) {
                        return $parameters['%time%'];
                    }
                }
            );

        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));

        $this->assertSame('00:00:00', $this->helper->toText($dateTime));
    }

    public function testFullConcat()
    {
        $this->setDefaultLocalTimezone('Europe/Paris');
        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', '2021-02-21 18:00:00', new \DateTimeZone('UTC'));
        $result   = $this->helper->toFullConcat($dateTime, 'UTC');
        $this->assertEquals($result, 'February 21, 2021 10:00 pm');
    }

    private function setDefaultLocalTimezone(string $timezone): void
    {
        $reflectedClass    = new \ReflectionClass($this->helper);
        $reflectedProperty = $reflectedClass->getProperty('helper');
        $reflectedProperty->setAccessible(true);
        $dateTimeHelper     = $reflectedProperty->getValue($this->helper);

        $reflectedClass     = new \ReflectionClass($dateTimeHelper);
        $reflectedProperty2 = $reflectedClass->getProperty('defaultLocalTimezone');
        $reflectedProperty2->setAccessible(true);
        $reflectedProperty2->setValue($dateTimeHelper, $timezone);
    }
}
