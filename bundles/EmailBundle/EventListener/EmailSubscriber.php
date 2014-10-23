<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\EventListener;

use Mautic\ApiBundle\Event\RouteEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\EmailBundle\Entity\DoNotEmail;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Event as Events;
use Mautic\EmailBundle\EmailEvents;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;
/**
 * Class EmailSubscriber
 *
 * @package Mautic\EmailBundle\EventListener
 */
class EmailSubscriber extends CommonSubscriber
{

    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            CoreEvents::GLOBAL_SEARCH       => array('onGlobalSearch', 0),
            CoreEvents::BUILD_COMMAND_LIST  => array('onBuildCommandList', 0),
            EmailEvents::EMAIL_POST_SAVE    => array('onEmailPostSave', 0),
            EmailEvents::EMAIL_POST_DELETE  => array('onEmailDelete', 0),
            CoreEvents::EMAIL_FAILED        => array('onEmailFailed', 0),
            CoreEvents::EMAIL_RESEND        => array('onEmailResend', 0),
            LeadEvents::TIMELINE_ON_GENERATE => array('onTimelineGenerate', 0)
        );
    }

    /**
     * @param MauticEvents\GlobalSearchEvent $event
     */
    public function onGlobalSearch(MauticEvents\GlobalSearchEvent $event)
    {
        /*
        $str = $event->getSearchString();
        if (empty($str)) {
            return;
        }

        $filter      = array("string" => $str, "force" => array());
        $permissions = $this->security->isGranted(
            array('email:emails:viewown', 'email:emails:viewother'),
            'RETURN_ARRAY'
        );
        if ($permissions['email:emails:viewown'] || $permissions['email:emails:viewother']) {
            if (!$permissions['email:emails:viewother']) {
                $filter['force'][] = array(
                    'column' => 'IDENTITY(e.createdBy)',
                    'expr'   => 'eq',
                    'value'  => $this->factory->getUser()->getId()
                );
            }

            $emails = $this->factory->getModel('email')->getEntities(
                array(
                    'limit'  => 5,
                    'filter' => $filter
                ));

            if (count($emails) > 0) {
                $emailResults = array();

                foreach ($emails as $email) {
                    $emailResults[] = $this->templating->renderResponse(
                        'MauticEmailBundle:Search:email.html.php',
                        array('email' => $email)
                    )->getContent();
                }
                if (count($emails) > 5) {
                    $emailResults[] = $this->templating->renderResponse(
                        'MauticEmailBundle:Search:email.html.php',
                        array(
                            'showMore'     => true,
                            'searchString' => $str,
                            'remaining'    => (count($emails) - 5)
                        )
                    )->getContent();
                }
                $emailResults['count'] = count($emails);
                $event->addResults('mautic.email.header.index', $emailResults);
            }
        }
        */
    }

    /**
     * @param MauticEvents\CommandListEvent $event
     */
    public function onBuildCommandList(MauticEvents\CommandListEvent $event)
    {
        if ($this->security->isGranted(array('email:emails:viewown', 'email:emails:viewother'), "MATCH_ONE")) {
            $event->addCommands(
                'mautic.email.header.index',
                $this->factory->getModel('email')->getCommandList()
            );
        }
    }

    /**
     * Add an entry to the audit log
     *
     * @param Events\EmailEvent $event
     */
    public function onEmailPostSave(Events\EmailEvent $event)
    {
        $email = $event->getEmail();
        if ($details = $event->getChanges()) {
            $log = array(
                "bundle"    => "email",
                "object"    => "email",
                "objectId"  => $email->getId(),
                "action"    => ($event->isNew()) ? "create" : "update",
                "details"   => $details,
                "ipAddress" => $this->request->server->get('REMOTE_ADDR')
            );
            $this->factory->getModel('core.auditLog')->writeToLog($log);
        }
    }

    /**
     * Add a delete entry to the audit log
     *
     * @param Events\EmailEvent $event
     */
    public function onEmailDelete(Events\EmailEvent $event)
    {
        $email = $event->getEmail();
        $log = array(
            "bundle"     => "email",
            "object"     => "email",
            "objectId"   => $email->deletedId,
            "action"     => "delete",
            "details"    => array('name' => $email->getSubject()),
            "ipAddress"  => $this->request->server->get('REMOTE_ADDR')
        );
        $this->factory->getModel('core.auditLog')->writeToLog($log);
    }

    /**
     * Process if an email has failed
     *
     * @param MauticEvents\EmailEvent $event
     * @param string                  $reason
     */
    public function onEmailFailed(MauticEvents\EmailEvent $event)
    {
        $message = $event->getMessage();

        if (isset($message->leadIdHash)) {
            $model = $this->factory->getModel('email');
            $stat  = $model->getEmailStatus($message->leadIdHash);

            if ($stat !== null) {
                $reason = $this->factory->getTranslator()->trans('mautic.email.dnc.failed', array(
                    "%subject%" => $message->getSubject()
                ));
                $model->setDoNotContact($stat, $reason);
            }
        }
    }

    /**
     * Process if an email is resent
     *
     * @param MauticEvents\EmailEvent $event
     */
    public function onEmailResend(MauticEvents\EmailEvent $event)
    {
        $message = $event->getMessage();

        if (isset($message->leadIdHash)) {
            $model = $this->factory->getModel('email');
            $stat  = $model->getEmailStatus($message->leadIdHash);
            if ($stat !== null) {
                $stat->upRetryCount();

                $retries = $stat->getRetryCount();
                if (true || $retries > 3) {
                    //tried too many times so just fail
                    $reason = $this->factory->getTranslator()->trans('mautic.email.dnc.retries', array(
                        "%subject%" => $message->getSubject()
                    ));
                    $model->setDoNotContact($stat, $reason);
                } else {
                    //set it to try again
                    $event->tryAgain();
                }

                $em = $this->factory->getEntityManager();
                $em->persist($stat);
                $em->flush();
            }
        }
    }

    /**
     * Compile events for the lead timeline
     *
     * @param LeadTimelineEvent $event
     */
    public function onTimelineGenerate(LeadTimelineEvent $event)
    {
        // Set available event types
        $eventTypeKeySent = 'email.sent';
        $eventTypeNameSent = $this->translator->trans('mautic.email.event.sent');
        $event->addEventType($eventTypeKeySent, $eventTypeNameSent);

        $eventTypeKeyRead = 'email.read';
        $eventTypeNameRead = $this->translator->trans('mautic.email.event.read');
        $event->addEventType($eventTypeKeyRead, $eventTypeNameRead);

        // Decide if those events are filtered
        $filter = $event->getEventFilter();
        $loadAllEvents = !isset($filter[0]);
        $sentEventFilterExists = in_array($eventTypeKeySent, $filter);
        $readEventFilterExists = in_array($eventTypeKeyRead, $filter);

        if (!$loadAllEvents && !($sentEventFilterExists || $readEventFilterExists)) {
            return;
        }

        $lead    = $event->getLead();
        $options = array('ipIds' => array(), 'filters' => $filter);

        /** @var \Mautic\CoreBundle\Entity\IpAddress $ip */
        foreach ($lead->getIpAddresses() as $ip) {
            $options['ipIds'][] = $ip->getId();
        }

        /** @var \Mautic\EmailBundle\Entity\StatRepository $statRepository */
        $statRepository = $this->factory->getEntityManager()->getRepository('MauticEmailBundle:Stat');

        $stats = $statRepository->getLeadStats($lead->getId(), $options);

        // Add the events to the event array
        foreach ($stats as $stat) {
            // Email Sent
            if (($loadAllEvents || $sentEventFilterExists) && $stat['dateSent']) {
                $event->addEvent(array(
                    'event'     => $eventTypeKeySent,
                    'eventLabel' => $eventTypeNameSent,
                    'timestamp' => $stat['dateSent'],
                    'extra'     => array(
                        'stats' => $stat
                    ),
                    'contentTemplate' => 'MauticEmailBundle:Timeline:index.html.php'
                ));
            }

            // Email read
            if (($loadAllEvents || $readEventFilterExists) && $stat['dateRead']) {
                $event->addEvent(array(
                    'event'     => $eventTypeKeyRead,
                    'eventLabel' => $eventTypeNameRead,
                    'timestamp' => $stat['dateRead'],
                    'extra'     => array(
                        'stats' => $stat
                    ),
                    'contentTemplate' => 'MauticEmailBundle:Timeline:index.html.php'
                ));
            }
        }
    }
}