<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PointBundle\Controller\Api;

use Mautic\ApiBundle\Controller\CommonApiController;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class PointApiController
 */
class PointApiController extends CommonApiController
{

    /**
     * {@inheritdoc}
     */
    public function initialize (FilterControllerEvent $event)
    {
        parent::initialize($event);
        $this->model            = $this->factory->getModel('point');
        $this->entityClass      = 'Mautic\PointBundle\Entity\Point';
        $this->entityNameOne    = 'point';
        $this->entityNameMulti  = 'points';
        $this->permissionBase   = 'point:points';
        $this->serializerGroups = array('pointDetails', 'categoryList', 'publishDetails');
    }

    /**
     * Obtains a list of points
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getEntitiesAction ()
    {
        return parent::getEntitiesAction();
    }

    /**
     * Obtains a specific point
     *
     * @param int $id Point ID
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function getEntityAction ($id)
    {
        return parent::getEntityAction($id);
    }
}
