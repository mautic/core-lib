<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\FormBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Event\DetermineWinnerEvent;
use Mautic\EmailBundle\Entity\Email;
use Mautic\FormBundle\FormEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\TranslatorInterface;

class DetermineWinnerSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param EntityManager       $em
     * @param TranslatorInterface $translator
     */
    public function __construct(EntityManager $em, TranslatorInterface $translator)
    {
        $this->em         = $em;
        $this->translator = $translator;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::ON_DETERMINE_SUBMISSION_RATE_WINNER => ['onDetermineSubmissionWinner', 0],
        ];
    }

    /**
     * Determines the winner of A/B test based on number of form submissions.
     *
     * @param DetermineWinnerEvent $event
     */
    public function onDetermineSubmissionWinner(DetermineWinnerEvent $event)
    {
        $repo       = $this->em->getRepository('MauticFormBundle:Submission');
        $parameters = $event->getParameters();
        $parent     = $parameters['parent'];
        $children   = $parameters['children'];

        //if this is an email A/B test, then link email to page to form submission
        //if it is a page A/B test, then link form submission to page
        $type = ($parent instanceof Email) ? 'email' : 'page';

        $ids = [$parent->getId()];

        foreach ($children as $c) {
            if ($c->isPublished()) {
                $id    = $c->getId();
                $ids[] = $id;
            }
        }

        $startDate = $parent->getVariantStartDate();
        if (null != $startDate && !empty($ids)) {
            $counts = ('page' == $type) ? $repo->getSubmissionCountsByPage($ids, $startDate) : $repo->getSubmissionCountsByEmail($ids, $startDate);

            $translator = $this->translator;
            if ($counts) {
                $submissions = $support = $data = [];
                $hasResults  = [];

                $submissionLabel = $translator->trans('mautic.form.abtest.label.submissions');
                $hitLabel        = ('page' == $type) ? $translator->trans('mautic.form.abtest.label.hits') : $translator->trans('mautic.form.abtest.label.sentemils');

                foreach ($counts as $stats) {
                    $submissionRate            = ($stats['total']) ? round(($stats['count'] / $stats['total']) * 100, 2) : 0;
                    $submissions[$stats['id']] = $submissionRate;
                    $data[$submissionLabel][]  = $stats['count'];
                    $data[$hitLabel][]         = $stats['total'];
                    $support['labels'][]       = $stats['id'].':'.$stats['name'].' ('.$submissionRate.'%)';
                    $hasResults[]              = $stats['id'];
                }

                //make sure that parent and published children are included
                if (!in_array($parent->getId(), $hasResults)) {
                    $data[$submissionLabel][] = 0;
                    $data[$hitLabel][]        = 0;
                    $support['labels'][]      = (('page' == $type) ? $parent->getTitle() : $parent->getName()).' (0%)';
                }

                foreach ($children as $c) {
                    if ($c->isPublished()) {
                        if (!in_array($c->getId(), $hasResults)) {
                            $data[$submissionLabel][] = 0;
                            $data[$hitLabel][]        = 0;
                            $support['labels'][]      = (('page' == $type) ? $c->getTitle() : $c->getName()).' (0%)';
                        }
                    }
                }
                $support['data'] = $data;

                //set max for scales
                $maxes = [];
                foreach ($support['data'] as $label => $data) {
                    $maxes[] = max($data);
                }
                $top                   = max($maxes);
                $support['step_width'] = (ceil($top / 10) * 10);

                //put in order from least to greatest just because
                asort($submissions);

                //who's the winner?
                $max = max($submissions);

                //get the page ids with the most number of submissions
                $winners = array_keys($submissions, $max);

                $event->setAbTestResults([
                    'winners'         => $winners,
                    'support'         => $support,
                    'basedOn'         => 'form.submissions',
                    'supportTemplate' => 'MauticPageBundle:SubscribedEvents\AbTest:bargraph.html.php',
                ]);

                return;
            }
        }

        $event->setAbTestResults([
            'winners' => [],
            'support' => [],
            'basedOn' => 'form.submissions',
        ]);
    }
}
