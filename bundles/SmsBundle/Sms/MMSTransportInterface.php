<?php

namespace Mautic\SmsBundle\Sms;

use Mautic\LeadBundle\Entity\Lead;

interface MMSTransportInterface
{
    /**
     * @param array<mixed> $media
     *
     * @return bool|string
     */
    public function sendMms(Lead $lead, string $content, array $media);
}
