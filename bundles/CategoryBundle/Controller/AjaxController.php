<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CategoryBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AjaxController
 *
 * @package Mautic\EmailBundle\Controller
 */
class AjaxController extends CommonAjaxController
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function categoryListAction(Request $request)
    {
        $bundle    = InputHelper::clean($request->query->get('bundle'));
        $filter    = InputHelper::clean($request->query->get('filter'));
        $results   = $this->factory->getModel('category')->getLookupResults($bundle, $filter, 10);
        $dataArray = array();
        foreach ($results as $r) {
            $dataArray[] = array(
                "label" => $r['title'] . " ({$r['id']})",
                "value" => $r['id']
            );
        }
        return $this->sendJsonResponse($dataArray);
    }
}