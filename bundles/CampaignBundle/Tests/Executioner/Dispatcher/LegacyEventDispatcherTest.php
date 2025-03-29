<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Tests\Executioner\Dispatcher;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CampaignBundle\Event\ExecutedBatchEvent;
use Mautic\CampaignBundle\Event\ExecutedEvent;
use Mautic\CampaignBundle\Event\FailedEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;
use Mautic\CampaignBundle\Executioner\Dispatcher\LegacyEventDispatcher;
use Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Tracker\ContactTracker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LegacyEventDispatcherTest extends TestCase
{
    /**
     * @var MockObject|EventDispatcherInterface
     */
    private MockObject $dispatcher;

    /**
     * @var MockObject|EventScheduler
     */
    private MockObject $scheduler;

    /**
     * @var MockObject|ContactTracker
     */
    private MockObject $contactTracker;

    /**
     * @var MockObject|AbstractEventAccessor
     */
    private MockObject $config;

    /**
     * @var MockObject|PendingEvent
     */
    private MockObject $pendingEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher     = $this->createMock(EventDispatcherInterface::class);
        $this->scheduler      = $this->createMock(EventScheduler::class);
        $this->contactTracker = $this->createMock(ContactTracker::class);
        $this->config         = $this->createMock(AbstractEventAccessor::class);
        $this->pendingEvent   = $this->createMock(PendingEvent::class);
    }

    public function testAllEventsAreFailedWithBadConfig(): void
    {
        $this->config->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $logs = new ArrayCollection([new LeadEventLog()]);

        $this->pendingEvent->expects($this->once())
            ->method('failAll');

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);
    }

    public function testPrimayLegacyEventsAreProcessed(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getConfig')
            ->willReturn(['eventName' => 'something']);

        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead(new Lead());
        $logs = new ArrayCollection([$leadEventLog]);

        // BC default is to have pass
        $this->pendingEvent->expects($this->once())
            ->method('pass');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        $matcher = $this->exactly(4);

        $this->dispatcher->expects($matcher)
            ->method('dispatch')->willReturnCallback(function (...$parameters) use ($matcher) {
            if ($matcher->getInvocationCount() === 1) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame('something', $parameters[1]);
            }
            if ($matcher->getInvocationCount() === 2) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTION, $parameters[1]); // @phpstan-ignore-line classConstant.deprecated  
            }
            if ($matcher->getInvocationCount() === 3) {
                $this->assertSame($this->isInstanceOf(ExecutedEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTED, $parameters[1]);
            }
            if ($matcher->getInvocationCount() === 4) {
                $this->assertSame($this->isInstanceOf(ExecutedBatchEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTED_BATCH, $parameters[1]);
            }
        });

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);
    }

    public function testPrimaryCallbackIsProcessed(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getConfig')
            ->willReturn(['callback' => [self::class, 'bogusCallback']]);

        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead(new Lead());
        $logs = new ArrayCollection([$leadEventLog]);

        // BC default is to have pass
        $this->pendingEvent->expects($this->once())
            ->method('pass');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        // Legacy execution event should dispatch
        $matcher = $this->exactly(3);

        // Legacy execution event should dispatch
        $this->dispatcher->expects($matcher)
            ->method('dispatch')->willReturnCallback(function (...$parameters) use ($matcher) {
            if ($matcher->getInvocationCount() === 1) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTION, $parameters[1]); // @phpstan-ignore-line classConstant.deprecated  
            }
            if ($matcher->getInvocationCount() === 2) {
                $this->assertSame($this->isInstanceOf(ExecutedEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTED, $parameters[1]);
            }
            if ($matcher->getInvocationCount() === 3) {
                $this->assertSame($this->isInstanceOf(ExecutedBatchEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTED_BATCH, $parameters[1]);
            }
        });

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);
    }

    public function testArrayResultAppendedToMetadata(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getConfig')
            ->willReturn(['eventName' => 'something']);

        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead(new Lead());
        $leadEventLog->setMetadata(['bar' => 'foo']);

        $logs = new ArrayCollection([$leadEventLog]);

        // BC default is to have pass
        $this->pendingEvent->expects($this->once())
            ->method('pass');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        // Legacy custom event should dispatch
        $matcher = $this->exactly(4);

        // Legacy custom event should dispatch
        $this->dispatcher->expects($matcher)
            ->method('dispatch')->willReturnCallback(function (...$parameters) use ($matcher) {
            if ($matcher->getInvocationCount() === 1) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame('something', $parameters[1]);
                return $this->returnCallback(
                    function (CampaignExecutionEvent $event, string $eventName) {
                        $event->setResult(['foo' => 'bar']);

                        return $event;
                    }
                );
            }
            if ($matcher->getInvocationCount() === 2) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTION, $parameters[1]); // @phpstan-ignore-line classConstant.deprecated  
                return $this->returnCallback(fn (CampaignExecutionEvent $event) => $event);
            }
            if ($matcher->getInvocationCount() === 3) {
                $this->assertSame($this->isInstanceOf(ExecutedEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTED, $parameters[1]);
                return $this->returnCallback(fn (ExecutedEvent $event) => $event);
            }
            if ($matcher->getInvocationCount() === 4) {
                $this->assertSame($this->isInstanceOf(ExecutedBatchEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTED_BATCH, $parameters[1]);
                return $this->returnCallback(fn (ExecutedBatchEvent $event) => $event);
            }
        });

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);

        $this->assertEquals(['bar' => 'foo', 'foo' => 'bar'], $leadEventLog->getMetadata());
    }

    public function testFailedResultAsFalseIsProcessed(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getConfig')
            ->willReturn(['eventName' => 'something']);

        $lead     = new Lead();
        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead($lead);
        $leadEventLog->setMetadata(['bar' => 'foo']);

        $logs = new ArrayCollection([$leadEventLog]);

        // Should fail because we're returning false
        $this->pendingEvent->expects($this->once())
            ->method('fail');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        // Legacy custom event should dispatch
        $matcher = $this->exactly(3);

        // Legacy custom event should dispatch
        $this->dispatcher->expects($matcher)
            ->method('dispatch')->willReturnCallback(function (...$parameters) use ($matcher) {
            if ($matcher->getInvocationCount() === 1) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame('something', $parameters[1]);
                return $this->returnCallback(
                    function (CampaignExecutionEvent $event, string $eventName) {
                        $event->setResult(false);

                        return $event;
                    }
                );
            }
            if ($matcher->getInvocationCount() === 2) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTION, $parameters[1]); // @phpstan-ignore-line classConstant.deprecated  
                return $this->returnCallback(fn (CampaignExecutionEvent $event) => $event);
            }
            if ($matcher->getInvocationCount() === 3) {
                $this->assertSame($this->isInstanceOf(FailedEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_FAILED, $parameters[1]);
                return $this->returnCallback(fn (FailedEvent $event) => $event);
            }
        });

        $this->scheduler->expects($this->once())
            ->method('rescheduleFailures');

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);
    }

    public function testFailedResultAsArrayIsProcessed(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getConfig')
            ->willReturn(['eventName' => 'something']);

        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead(new Lead());

        $logs = new ArrayCollection([$leadEventLog]);

        // Should fail because we're returning false
        $this->pendingEvent->expects($this->once())
            ->method('fail');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        // Legacy custom event should dispatch
        $matcher = $this->exactly(3);

        // Legacy custom event should dispatch
        $this->dispatcher->expects($matcher)
            ->method('dispatch')->willReturnCallback(function (...$parameters) use ($matcher) {
            if ($matcher->getInvocationCount() === 1) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame('something', $parameters[1]);
                return $this->returnCallback(
                    function (CampaignExecutionEvent $event, string $eventName) {
                        $event->setResult(['result' => false, 'foo' => 'bar']);

                        return $event;
                    }
                );
            }
            if ($matcher->getInvocationCount() === 2) {
                $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_EXECUTION, $parameters[1]); // @phpstan-ignore-line classConstant.deprecated  
                return $this->returnCallback(fn (CampaignExecutionEvent $event) => $event);
            }
            if ($matcher->getInvocationCount() === 3) {
                $this->assertSame($this->isInstanceOf(FailedEvent::class), $parameters[0]);
                $this->assertSame(CampaignEvents::ON_EVENT_FAILED, $parameters[1]);
                return $this->returnCallback(fn (FailedEvent $event) => $event);
            }
        });

        $this->scheduler->expects($this->once())
            ->method('rescheduleFailures');

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);
    }

    public function testPassWithErrorIsHandled(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getConfig')
            ->willReturn(['eventName' => 'something']);

        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead(new Lead());
        $leadEventLog->setMetadata(['bar' => 'foo']);

        $logs = new ArrayCollection([$leadEventLog]);

        // Should pass but with an error logged
        $this->pendingEvent->expects($this->once())
            ->method('passWithError');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        // Legacy custom event should dispatch
        $matcher = $this->exactly(1);

        // Legacy custom event should dispatch
        $this->dispatcher->expects($matcher)->method('dispatch')
            ->willReturnCallback(function (...$parameters) use ($matcher) {
                if ($matcher->getInvocationCount() === 1) {
                    $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                    $this->assertSame('something', $parameters[1]);
                    return $this->returnCallback(function (CampaignExecutionEvent $event, string $eventName): object {
                        $event->setResult(['failed' => 1, 'reason' => 'because']);
    
                        return $event;
                    });
                }
            });

        $this->scheduler->expects($this->never())
            ->method('rescheduleFailure');

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);
    }

    public function testLogIsPassed(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getConfig')
            ->willReturn(['eventName' => 'something']);

        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead(new Lead());
        $leadEventLog->setMetadata(['bar' => 'foo']);

        $logs = new ArrayCollection([$leadEventLog]);

        // Should fail because we're returning false
        $this->pendingEvent->expects($this->once())
            ->method('pass');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        // Should pass
        $matcher = $this->exactly(1);

        // Should pass
        $this->dispatcher->expects($matcher)->method('dispatch')
            ->willReturnCallback(function (...$parameters) use ($matcher) {
                if ($matcher->getInvocationCount() === 1) {
                    $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                    $this->assertSame('something', $parameters[1]);
                    return $this->returnCallback(
                        function (CampaignExecutionEvent $event, $eventName) {
                            $event->setResult(true);
    
                            return $event;
                        });
                }
            });

        $this->scheduler->expects($this->never())
            ->method('rescheduleFailure');

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, false, $this->pendingEvent);
    }

    public function testLegacyEventDispatchedForConvertedBatchActions(): void
    {
        $this->config->expects($this->exactly(1))
            ->method('getConfig')
            ->willReturn(['eventName' => 'something']);

        $event    = new Event();
        $campaign = new Campaign();
        $event->setCampaign($campaign);
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setEvent($event);
        $leadEventLog->setLead(new Lead());
        $leadEventLog->setMetadata(['bar' => 'foo']);

        $logs = new ArrayCollection([$leadEventLog]);

        // Should never be called
        $this->pendingEvent->expects($this->never())
            ->method('pass');

        $this->contactTracker->expects($this->exactly(2))
            ->method('setSystemContact');
        $matcher = $this->exactly(1);

        $this->dispatcher->expects($matcher)->method('dispatch')
            ->willReturnCallback(function (...$parameters) use ($matcher) {
                if ($matcher->getInvocationCount() === 1) {
                    $this->assertSame($this->isInstanceOf(CampaignExecutionEvent::class), $parameters[0]);
                    $this->assertSame('something', $parameters[1]);
                    return $this->returnCallback(
                        fn (CampaignExecutionEvent $event) => $event->setResult(true)
                    );
                }
            });

        $this->getLegacyEventDispatcher()->dispatchCustomEvent($this->config, $logs, true, $this->pendingEvent);
    }

    private function getLegacyEventDispatcher(): LegacyEventDispatcher
    {
        return new LegacyEventDispatcher(
            $this->dispatcher,
            $this->scheduler,
            new NullLogger(),
            $this->contactTracker
        );
    }

    public static function bogusCallback(): bool
    {
        return true;
    }
}
