<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Helper;

use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Entity\Stat;

class BotRatioHelper
{
    /**
     * @var float
     */
    private $botRatioThreshold;

    /**
     * @var int
     */
    private $timeFromEmailThreshold;

    /**
     * @var array<string>
     */
    private $blockedUserAgents;

    /**
     * @var array<string>
     */
    private $blockedIPAddresses;

    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->botRatioThreshold      = $coreParametersHelper->get('bot_helper_bot_ratio_threshold', 0.6);
        $this->timeFromEmailThreshold = $coreParametersHelper->get('bot_helper_time_email_threshold', 2);
        $this->blockedUserAgents      = $coreParametersHelper->get('bot_helper_blocked_user_agents', []);
        $this->blockedIPAddresses     = $coreParametersHelper->get('bot_helper_blocked_ip_addresses', []);
    }

    public function isHitByBot(Stat $emailStat, \DateTimeInterface $emailHitDateTime, IpAddress $ipAddress, string $userAgent): bool
    {
        $totalPoints = (int) $this->isUnderTimeThreshold($emailStat, $emailHitDateTime) +
            (int) $this->isIpInIgnoreList($ipAddress) +
            (int) $this->isUserAgentInIgnoreList($userAgent);

        return $totalPoints / 3 >= $this->botRatioThreshold;
    }

    private function isUnderTimeThreshold(Stat $emailStat, \DateTimeInterface $emailHitDateTime): bool
    {
        $timeFromSend = $emailHitDateTime->getTimestamp() - $emailStat->getDateSent()->getTimestamp();

        return $timeFromSend < $this->timeFromEmailThreshold;
    }

    private function isIpInIgnoreList(IpAddress $ipAddress): bool
    {
        // Create a clone so that setting up do not track IP list here will not update original blocked Ip List
        $ipAddressLocal = clone $ipAddress;
        $ipAddressLocal->setDoNotTrackList($this->blockedIPAddresses);

        return !$ipAddressLocal->isTrackable();
    }

    private function isUserAgentInIgnoreList(string $userAgent): bool
    {
        foreach ($this->blockedUserAgents as $blockedUserAgent) {
            if (str_contains($userAgent, $blockedUserAgent)) {
                return true;
            }
        }

        return false;
    }
}
