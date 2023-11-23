<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\ProcessSignal\Exception;

use Mautic\CoreBundle\ProcessSignal\ProcessSignalState;

class SignalCaughtException extends \Exception
{
    private ?ProcessSignalState $state;

    public function __construct(int $signal, ProcessSignalState $state = null)
    {
        parent::__construct(sprintf('Signal received: "%d"', $signal), $signal);
        $this->state = $state;
    }

    public function getState(): ?ProcessSignalState
    {
        return $this->state;
    }
}
