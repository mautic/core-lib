<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Stat;
use Symfony\Component\HttpFoundation\Request;

final class BotRatioHelperFunctionalTest extends MauticMysqlTestCase
{
    private const DO_NOT_TRACK_IP = '218.30.65.10';

    private const BOT_BLOCKED_IP = '218.30.65.11';

    private const IP_NOT_IN_ANY_BLOCK_LIST = '218.30.65.12';

    private const IP_NOT_IN_ANY_BLOCK_LIST2 = '218.30.65.111';

    protected function setUp(): void
    {
        $this->configParams['do_not_track_ips']                = [self::DO_NOT_TRACK_IP];
        $this->configParams['bot_helper_blocked_ip_addresses'] = [self::BOT_BLOCKED_IP];
        parent::setUp();
    }

    /**
     * @dataProvider hitBotScenariosProvider
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function testIsHitByBotFunctional(string $trackingHash, string $sentBefore, string $userAgent, string $ipAddress, bool $isRead): void
    {
        $stat          = new Stat();
        $emailSendTime = new \DateTime();
        $stat->setDateSent($emailSendTime->modify($sentBefore));
        $stat->setTrackingHash($trackingHash);
        $stat->setEmailAddress('lukas@mautic.test');
        $this->em->persist($stat);
        $this->em->flush();
        $statId = $stat->getId();

        $server = [
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR'     => $ipAddress,
        ];
        $this->client->request(Request::METHOD_GET, '/email/'.$stat->getTrackingHash().'.gif', [], [], $server);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $updatedStat = $this->em->getRepository(Stat::class)->findOneBy(['id'=>$statId]);
        $this->assertSame($isRead, $updatedStat->getIsRead());
        if ($isRead) {
            $this->assertNotNull($updatedStat->getLastOpened());
        } else {
            $this->assertNull($updatedStat->getLastOpened());
        }
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public function hitBotScenariosProvider(): iterable
    {
        // $trackingHash, $sentBefore, $userAgent, $ipAddress, $isRead
        yield 'All good' => ['test_hash_bot_ratio_1', '-80 second', 'Mozilla/5.0', self::IP_NOT_IN_ANY_BLOCK_LIST, true];
        yield 'Time and User' => ['test_hash_bot_ratio_2', '+80 second', 'AHC/2.1', self::IP_NOT_IN_ANY_BLOCK_LIST, false];
        yield 'Time and IP' => ['test_hash_bot_ratio_3', '+80 second', 'Mozilla/5.0', self::BOT_BLOCKED_IP, false];
        yield 'Permanently blocked IP' => ['test_hash_bot_ratio_4', '-80 second', 'Mozilla/5.0', self::DO_NOT_TRACK_IP, false];
        yield 'Bot Blocked IP address only' => ['test_hash_bot_ratio_5', '-80 second', 'Mozilla/5.0', self::BOT_BLOCKED_IP, true];
        yield 'Bot Blocked User Agent only' => ['test_hash_bot_ratio_6', '-80 second', 'AHC/2.1', self::IP_NOT_IN_ANY_BLOCK_LIST, true];
        yield 'Time Only' => ['test_hash_bot_ratio_7', '+80 second', 'Mozilla/5.0', self::IP_NOT_IN_ANY_BLOCK_LIST, true];
        yield 'Time and Bot User Agent and Bot IP' => ['test_hash_bot_ratio_8', '+80 second', 'AHC/2.1', self::BOT_BLOCKED_IP, false];
        yield 'Bot User Agent and Bot IP' => ['test_hash_bot_ratio_9', '-80 second', 'AHC/2.1', self::BOT_BLOCKED_IP, false];
        yield 'Permanently blocked User Agent' => ['test_hash_bot_ratio_10', '-80 second', 'MSNBOT', self::IP_NOT_IN_ANY_BLOCK_LIST2, false];
    }
}
