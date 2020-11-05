<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AllydeBundle\Tests\Beanstalk\Tube;

use Mautic\AllydeBundle\Beanstalk\Tube\CustomFieldTube;
use PHPUnit\Framework\TestCase;

class CustomFieldTubeTest extends TestCase
{
    public function testGetDistinctJobs()
    {
        $this->assertContains('customField.createLeadColumn', CustomFieldTube::getDistinctJobs());
        $this->assertContains('customField.updateLeadColumn', CustomFieldTube::getDistinctJobs());
        $this->assertContains('customField.deleteLeadColumn', CustomFieldTube::getDistinctJobs());
    }
}
