<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailEvent;
use Mautic\PageBundle\Entity\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmailDefaultsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_PRE_SAVE => ['onEmailPreSave', 0],
        ];
    }

    public function onEmailPreSave(EmailEvent $event): void
    {
        $email = $event->getEmail();

        if (!$event->isNew() || $email->getIsClone()) {
            return;
        }

        $changesBefore = $email->getChanges();

        if (null === $email->getPreferenceCenter()) {
            $defaultId = $this->coreParametersHelper->get('email_default_preference_center_id');
            if (!empty($defaultId)) {
                $page = $this->entityManager->find(Page::class, $defaultId);
                if ($page instanceof Page) {
                    $email->setPreferenceCenter($page);
                }
            }
        }

        if (empty($email->getUtmTags())) {
            $utmTags = [
                'utmSource'   => $this->coreParametersHelper->get('email_default_utm_source'),
                'utmMedium'   => $this->coreParametersHelper->get('email_default_utm_medium'),
                'utmCampaign' => $this->coreParametersHelper->get('email_default_utm_campaign'),
                'utmContent'  => $this->coreParametersHelper->get('email_default_utm_content'),
            ];

            $filtered = array_filter($utmTags, static fn ($tag): bool => null !== $tag && '' !== $tag);
            if ($filtered) {
                $email->setUtmTags($filtered);
            }
        }

        // Restore only the changes that existed before defaults were applied,
        // so system-applied defaults don't appear as user edits in the audit log.
        $email->setChanges($changesBefore);
    }
}
