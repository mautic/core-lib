<?php

namespace Mautic\CoreBundle\Tests\Unit\Model;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Event;

class FormModelTest extends TestCase
{
    /**
     * @var FormModel
     */
    private $formModel;

    /**
     * @var MockObject|EntityManager
     */
    private $entityManagerMock;

    /**
     * @var MockObject|UserHelper
     */
    private $userHelperMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManagerMock = $this->createMock(EntityManager::class);
        $this->userHelperMock    = $this->createMock(UserHelper::class);
        $this->formModel         = new FormModel();
        $this->formModel->setEntityManager($this->entityManagerMock);
        $this->formModel->setUserHelper($this->userHelperMock);
    }

    public function testSaveEntities(): void
    {
        $leads = [];
        for ($x = 0; $x < 30; ++$x) {
            $lead = new Lead();
            $lead->setEmail(sprintf('test%s@test.cz', $x));
            $leads[] = $lead;
        }

        $this->entityManagerMock->expects($this->exactly(2))
            ->method('flush');

        $this->userHelperMock->expects($this->exactly(60))
            ->method('getUser')
            ->willReturn(new User());

        $formModel           = new class() extends FormModel {
            private $actions = [];

            protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
            {
                $this->actions[] = $action;

                return $event;
            }

            protected function dispatchBatchEvent(string $action, array &$entitiesBatchParams, Event $event = null): ?Event
            {
                $this->actions[] = $action;

                return $event;
            }

            public function getActionsSent(): array
            {
                return $this->actions;
            }
        };
        $formModel->setEntityManager($this->entityManagerMock);
        $formModel->setUserHelper($this->userHelperMock);
        $formModel->saveEntities($leads);
        $actionsSent   = $formModel->getActionsSent();
        $countDispatch = 0;
        foreach ($actionsSent as $action) {
            if ($countDispatch < 30) {
                $this->assertSame('pre_save', $action);
            } elseif (30 === $countDispatch) {
                $this->assertSame('pre_batch_save', $action);
            } elseif ($countDispatch > 30 && $countDispatch < 61) {
                $this->assertSame('post_save', $action);
            } elseif (61 === $countDispatch) {
                $this->assertSame('post_batch_save', $action);
            }
            ++$countDispatch;
        }
    }
}
