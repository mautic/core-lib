<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Helper;

use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Helper\BotRatioHelper;
use PHPUnit\Framework\TestCase;

final class BotRatioHelperTest extends TestCase
{
    /**
     * @dataProvider hitBotScenariosProvider
     *
     * @param array<string> $ipDoNotTrackList
     * @param array<string> $blockedUserAgents
     */
    public function testIsHitByBot(
        string $sentBefore,
        int $botHelperTimeEmailThreshold,
        string $ipAddressString,
        array $ipDoNotTrackList,
        string $userAgent,
        array $blockedUserAgents,
        float $botHelperBotRatioThreshold,
        bool $isBot,
    ): void {
        // Threshold
        $coreParametersHelperMock = $this->createMock(CoreParametersHelper::class);
        $coreParametersHelperMock->expects($this->at(0))
            ->method('get')
            ->with('bot_helper_bot_ratio_threshold', 0.6)
            ->willReturn($botHelperBotRatioThreshold);
        // Time
        $emailHitDateTime = new \DateTime();
        $emailSent        = clone $emailHitDateTime;
        $emailSent->modify($sentBefore);
        $emailStatMock = $this->createMock(Stat::class);
        $emailStatMock->expects($this->once())
            ->method('getDateSent')
            ->willReturn($emailSent);
        $coreParametersHelperMock->expects($this->at(1))
            ->method('get')
            ->with('bot_helper_time_email_threshold', 2)
            ->willReturn($botHelperTimeEmailThreshold);
        // IP
        $ipAddress = new IpAddress($ipAddressString);
        // User Agent
        $coreParametersHelperMock->expects($this->at(2))
            ->method('get')
            ->with('bot_helper_blocked_user_agents', [])
            ->willReturn($blockedUserAgents);

        $coreParametersHelperMock->expects($this->at(3))
            ->method('get')
            ->with('bot_helper_blocked_ip_addresses', [])
            ->willReturn($ipDoNotTrackList);

        $botRatioHelper   = new BotRatioHelper($coreParametersHelperMock);
        $isEvaluatedAsBot = $botRatioHelper->isHitByBot($emailStatMock, $emailHitDateTime, $ipAddress, $userAgent);
        $this->assertSame($isBot, $isEvaluatedAsBot);
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public function hitBotScenariosProvider(): iterable
    {
        // sentBefore, botHelperTimeEmailThreshold, ipAddress, ipDoNotTrackList, userAgent, blockedUserAgents, botHelperBotRatioThreshold, isBot
        yield 'Time and IP' => ['-1 second', 2, '217.30.65.82', ['217.30.65.*'], 'Mozilla/5.0', [], 0.6, true];
        yield 'Time and User Agent' => ['-1 second', 2, '217.30.65.82', [], 'Mozilla/5.0', ['Mozilla'], 0.6, true];
        yield 'Just User Agent' => ['-3 second', 2, '217.30.65.82', [], 'Mozilla/5.0', ['Mozilla'], 0.6, false];
        yield 'All' => ['-1 second', 2, '217.30.65.82', ['217.30.65.*'], 'Mozilla/5.0', ['Mozilla'], 1.0, true];
        yield 'Just Time and IP' => ['-1 second', 2, '217.30.65.82', ['217.30.65.*'], 'Molla/5.0', ['Mozilla'], 1.0, false];
        yield 'Just Time and IP 2' => ['-1 second', 2, '217.30.65.82', ['217.30.65.*'], (string) null, ['Mozilla'], 1.0, false];
    }
}
