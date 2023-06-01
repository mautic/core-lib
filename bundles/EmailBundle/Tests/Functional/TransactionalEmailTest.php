<?php

namespace Mautic\EmailBundle\Tests\Functional;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Entity\StatRepository;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Helper\SMimeHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\EmailBundle\MonitoredEmail\Mailbox;
use Mautic\EmailBundle\Tests\Helper\Transport\SmtpTransport;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Monolog\Logger;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Swift_Mailer;

class TransactionalEmailTest extends MauticMysqlTestCase
{
    public function testNoUnsubscribeTextAndUrl(): void
    {
        /** @var MauticFactory|MockObject $mockFactory */
//        $mockFactory         = $this->createMock(MauticFactory::class);
        $mockFactory = $this->getMockFactory(true);

        $router          = $this->createMock(UrlGeneratorInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockFactory->expects(self::once())
            ->method('getDispatcher')
            ->willReturn($eventDispatcher);
        $mockFactory->expects(self::exactly(3))
            ->method('getRouter')
            ->willReturn($router);

        $transport   = new SmtpTransport();
        $swiftMailer = new Swift_Mailer($transport);

        $sMimeHelper         = $this->createMock(SMimeHelper::class);

        $mailer = new MailHelper($mockFactory, $swiftMailer, $sMimeHelper, ['nobody@nowhere.com' => 'No Body']);
        $mailer->addTo('send@nobody.com');
        $mailer->setIdHash();

        $email = new Email();
        $email->setSubject('Test');
        $email->setCustomHtml('{unsubscribe_text}{unsubscribe_url}');
        $mailer->setEmail($email);
        $mailer->send(true);

        Assert::assertNotContains('unsubscribe', $mailer->getBody());
    }

    /**
     * @param mixed[] $parameterMap
     */
    protected function getMockFactory(bool $mailIsOwner = true, array $parameterMap = []): MauticFactory
    {
        $mockLeadRepository = $this->getMockBuilder(LeadRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockLeadRepository->method('getLeadOwner')
            ->will(
                $this->returnValueMap(
                    [
                        [1, ['id' => 1, 'email' => 'owner1@owner.com', 'first_name' => '', 'last_name' => '', 'signature' => 'owner 1']],
                        [2, ['id' => 2, 'email' => 'owner2@owner.com', 'first_name' => '', 'last_name' => '', 'signature' => 'owner 2']],
                        [3, ['id' => 3, 'email' => 'owner3@owner.com', 'first_name' => 'John', 'last_name' => 'S&#39;mith', 'signature' => 'owner 2']],
                    ]
                )
            );

        $mockLeadModel = $this->getMockBuilder(LeadModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockLeadModel->method('getRepository')
            ->willReturn($mockLeadRepository);

        $mockFactory = $this->createMock(MauticFactory::class);

        $parameterMap = array_merge(
            [
                ['mailer_return_path', false, null],
                ['mailer_spool_type', false, 'memory'],
                ['mailer_is_owner', false, $mailIsOwner],
            ],
            $parameterMap
        );

        $mockFactory->method('getParameter')
            ->will(
                $this->returnValueMap($parameterMap)
            );
        $mockFactory->method('getModel')
            ->will(
                $this->returnValueMap(
                    [
                        ['lead', $mockLeadModel],
                    ]
                )
            );

        $mockLogger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFactory->method('getLogger')
            ->willReturn($mockLogger);

        $mockMailboxHelper = $this->getMockBuilder(Mailbox::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMailboxHelper->method('isConfigured')
            ->willReturn(false);

        $mockFactory->method('getHelper')
            ->will(
                $this->returnValueMap(
                    [
                        ['mailbox', $mockMailboxHelper],
                    ]
                )
            );

        return $mockFactory;
    }
}