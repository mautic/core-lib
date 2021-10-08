<?php

declare(strict_types=1);

/*
 * @copyright   2021 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Event;

use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class LeadGetCurrentEvent extends Event
{
    /**
     * @var ?Request
     */
    private $request;

    /**
     * @var ?Lead
     */
    private $contact;

    public function __construct(?Request $request)
    {
        $this->request = $request;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function getContact(): ?Lead
    {
        return $this->contact;
    }

    public function setContact(?Lead $contact): void
    {
        $this->contact = $contact;
    }
}
