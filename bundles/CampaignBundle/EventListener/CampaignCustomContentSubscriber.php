<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Model\AuditLogModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class CampaignCustomContentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogModel $auditLogModel,
        private Environment $twig,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectViewCustomContent', 0],
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        $parameters = $customContentEvent->getVars();
        $campaign   = $parameters['campaign'] ?? null;

        if (!$campaign instanceof Campaign) {
            return;
        }

        $viewName = '@MauticCampaign/Campaign/details.html.twig';
        if ($customContentEvent->checkContext($viewName, 'tabs')) {
            $content                   = $this->twig->render(
                '@MauticCampaign/Campaign/Tab/recent-activity-tab.html.twig',
            );
            $customContentEvent->addContent($content);
        } elseif ($customContentEvent->checkContext($viewName, 'tabs.content')) {
            $logs    = $this->auditLogModel->getLogForObject('campaign', $campaign->getId());
            $this->prepareChangeSetLogs($logs);

            $content = $this->twig->render(
                '@MauticCampaign/Campaign/Tab/recent-activity-tabcontent.html.twig',
                [
                    'campaign' => $campaign,
                    'logs'     => $logs,
                ]
            );
            $customContentEvent->addContent($content);
        }
    }

    private function prepareChangeSetLogs(mixed &$logs): void
    {
        foreach ($logs as &$log) {
            $changes = [];
            if ('create' === $log['action']) {
                continue;
            }
            $changeSet = $log['details']['events'];

            if (isset($changeSet['removed'])) {
                $key              = key($changeSet['removed']);
                $changes['title'] = 'Removed: Id:'.$key.' name: '.$changeSet['removed'][$key];
                $changes['item']  = [];
            }

            if (isset($changeSet['added'])) {
                $key              = key($changeSet['added']);
                $changes['title'] = 'Added/Updated: Id:'.$key;
                $changes['item']  = [];
                foreach ($changeSet['added'] as $key => $change) {
                    $subChanges = $change[1];
                    foreach ($subChanges as $subKey => $subChange) {
                        $changes['item'][] = [
                            'field'    => $subKey,
                            'oldValue' => json_encode($subChange[0]),
                            'newValue' => json_encode($subChange[1]),
                        ];
                    }
                }
            }

            $log['changes'] = $changes;
        }
    }
}
