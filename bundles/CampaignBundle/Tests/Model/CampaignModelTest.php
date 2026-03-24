<?php

namespace Mautic\CampaignBundle\Tests\Model;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Tests\CampaignTestAbstract;
use Mautic\LeadBundle\Entity\LeadList;

class CampaignModelTest extends CampaignTestAbstract
{
    public function testGetSourceListsWithNull(): void
    {
        $model = $this->initCampaignModel();
        $lists = $model->getSourceLists();
        $this->assertTrue(isset($lists['lists']));
        $this->assertSame([parent::$mockId => parent::$mockName], $lists['lists']);
        $this->assertTrue(isset($lists['forms']));
        $this->assertSame([parent::$mockId => parent::$mockName], $lists['forms']);
    }

    public function testGetSourceListsWithLists(): void
    {
        $model = $this->initCampaignModel();
        $lists = $model->getSourceLists('lists');
        $this->assertSame([parent::$mockId => parent::$mockName], $lists);
    }

    public function testGetSourceListsWithForms(): void
    {
        $model = $this->initCampaignModel();
        $lists = $model->getSourceLists('forms');
        $this->assertSame([parent::$mockId => parent::$mockName], $lists);
    }

    public function testSetLeadSourcesAddsLeadListById(): void
    {
        $model    = $this->initCampaignModel();
        $campaign = $this->createMock(Campaign::class);
        $leadList = $this->createMock(LeadList::class);

        $this->entityManager->expects($this->once())
            ->method('find')
            ->with(LeadList::class, parent::$mockId)
            ->willReturn($leadList);

        $campaign->expects($this->once())
            ->method('addList')
            ->with($leadList);

        $model->setLeadSources($campaign, ['lists' => [parent::$mockId => parent::$mockName]], []);
    }

    public function testSetLeadSourcesIgnoresNonNumericLeadListIdentifier(): void
    {
        $model    = $this->initCampaignModel();
        $campaign = $this->createMock(Campaign::class);

        $this->entityManager->expects($this->never())
            ->method('find');

        $campaign->expects($this->never())
            ->method('addList');

        $model->setLeadSources($campaign, ['lists' => ['list-one' => 0]], []);
    }
}
