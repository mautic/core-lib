<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\SkipMigration;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;
use Mautic\CoreBundle\ParametersStorage\ParametersStorage;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\SmsBundle\Form\Type\ConfigType;

final class Version20221128145933 extends AbstractMauticMigration
{
    /**
     * @throws SkipMigration
     */
    public function preUp(Schema $schema): void
    {
        /** @var IntegrationHelper $integrationHelper */
        $integrationHelper = $this->container->get('mautic.helper.integration');
        $integration       = $integrationHelper->getIntegrationObject('Twilio');
        $settings          = $integration->getIntegrationSettings()->getFeatureSettings();
        if (empty($settings['disable_trackable_urls'])) {
            throw new SkipMigration('Schema includes this migration');
        }
    }

    public function up(Schema $schema): void
    {
        /** @var ParametersStorage $parameterStorage */
        $parameterStorage = $this->container->get('mautic.parameters.storage');
        $parameterStorage->getStorage()->write([ConfigType::SMS_DISABLE_TRACKABLE_URLS => 1]);
    }
}
