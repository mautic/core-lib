<?php

namespace Mautic\NotificationBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\CoreBundle\Form\Type\DateRangeType;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Controller\EntityContactsTrait;
use Mautic\NotificationBundle\Entity\Notification;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends AbstractFormController
{
    use EntityContactsTrait;

    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page = 1)
    {
        /** @var \Mautic\NotificationBundle\Model\NotificationModel $model */
        $model = $this->getModel('notification');

        //set some permissions
        $permissions = $this->get('mautic.security')->isGranted(
            [
                'notification:notifications:viewown',
                'notification:notifications:viewother',
                'notification:notifications:create',
                'notification:notifications:editown',
                'notification:notifications:editother',
                'notification:notifications:deleteown',
                'notification:notifications:deleteother',
                'notification:notifications:publishown',
                'notification:notifications:publishother',
            ],
            'RETURN_ARRAY'
        );

        if (!$permissions['notification:notifications:viewown'] && !$permissions['notification:notifications:viewother']) {
            return $this->accessDenied();
        }

        if ('POST' == $this->request->getMethod()) {
            $this->setListFilters();
        }

        $session = $this->get('session');

        //set limits
        $limit = $session->get('mautic.notification.limit', $this->coreParametersHelper->get('default_pagelimit'));
        $start = (1 === $page) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $session->get('mautic.notification.filter', ''));
        $session->set('mautic.notification.filter', $search);

        $filter = [
            'string' => $search,
            'where'  => [
                [
                    'expr' => 'eq',
                    'col'  => 'mobile',
                    'val'  => 0,
                ],
            ],
        ];

        if (!$permissions['notification:notifications:viewother']) {
            $filter['force'][] =
                ['column' => 'e.createdBy', 'expr' => 'eq', 'value' => $this->user->getId()];
        }

        $orderBy    = $session->get('mautic.notification.orderby', 'e.name');
        $orderByDir = $session->get('mautic.notification.orderbydir', 'DESC');

        $notifications = $model->getEntities(
            [
                'start'      => $start,
                'limit'      => $limit,
                'filter'     => $filter,
                'orderBy'    => $orderBy,
                'orderByDir' => $orderByDir,
            ]
        );

        $count = count($notifications);
        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            if (1 === $count) {
                $lastPage = 1;
            } else {
                $lastPage = (floor($count / $limit)) ?: 1;
            }

            $session->set('mautic.notification.page', $lastPage);
            $returnUrl = $this->generateUrl('mautic_notification_index', ['page' => $lastPage]);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => ['page' => $lastPage],
                    'contentTemplate' => 'Mautic\NotificationBundle\Controller\NotificationController::indexAction',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_notification_index',
                        'mauticContent' => 'notification',
                    ],
                ]
            );
        }
        $session->set('mautic.notification.page', $page);

        return $this->delegateView(
            [
                'viewParameters' => [
                    'searchValue' => $search,
                    'items'       => $notifications,
                    'totalItems'  => $count,
                    'page'        => $page,
                    'limit'       => $limit,
                    'tmpl'        => $this->request->get('tmpl', 'index'),
                    'permissions' => $permissions,
                    'model'       => $model,
                    'security'    => $this->get('mautic.security'),
                ],
                'contentTemplate' => 'MauticNotificationBundle:Notification:list.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_notification_index',
                    'mauticContent' => 'notification',
                    'route'         => $this->generateUrl('mautic_notification_index', ['page' => $page]),
                ],
            ]
        );
    }

    /**
     * Loads a specific form into the detailed panel.
     *
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        /** @var \Mautic\NotificationBundle\Model\NotificationModel $model */
        $model    = $this->getModel('notification');
        $security = $this->get('mautic.security');

        /** @var \Mautic\NotificationBundle\Entity\Notification $notification */
        $notification = $model->getEntity($objectId);
        //set the page we came from
        $page = $this->get('session')->get('mautic.notification.page', 1);

        if (null === $notification) {
            //set the return URL
            $returnUrl = $this->generateUrl('mautic_notification_index', ['page' => $page]);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => ['page' => $page],
                    'contentTemplate' => 'Mautic\NotificationBundle\Controller\NotificationController::indexAction',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_notification_index',
                        'mauticContent' => 'notification',
                    ],
                    'flashes' => [
                        [
                            'type'    => 'error',
                            'msg'     => 'mautic.notification.error.notfound',
                            'msgVars' => ['%id%' => $objectId],
                        ],
                    ],
                ]
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'notification:notifications:viewown',
            'notification:notifications:viewother',
            $notification->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        }

        // Audit Log
        $logs = $this->getModel('core.auditlog')->getLogForObject('notification', $notification->getId(), $notification->getDateAdded());

        // Init the date range filter form
        $dateRangeValues = $this->request->get('daterange', []);
        $action          = $this->generateUrl('mautic_notification_action', ['objectAction' => 'view', 'objectId' => $objectId]);
        $dateRangeForm   = $this->get('form.factory')->create(DateRangeType::class, $dateRangeValues, ['action' => $action]);
        $entityViews     = $model->getHitsLineChartData(
            null,
            new \DateTime($dateRangeForm->get('date_from')->getData()),
            new \DateTime($dateRangeForm->get('date_to')->getData()),
            null,
            ['notification_id' => $notification->getId()]
        );

        // Get click through stats
        $trackableLinks = $model->getNotificationClickStats($notification->getId());

        return $this->delegateView([
            'returnUrl'      => $this->generateUrl('mautic_notification_action', ['objectAction' => 'view', 'objectId' => $notification->getId()]),
            'viewParameters' => [
                'notification' => $notification,
                'trackables'   => $trackableLinks,
                'logs'         => $logs,
                'permissions'  => $security->isGranted([
                    'notification:notifications:viewown',
                    'notification:notifications:viewother',
                    'notification:notifications:create',
                    'notification:notifications:editown',
                    'notification:notifications:editother',
                    'notification:notifications:deleteown',
                    'notification:notifications:deleteother',
                    'notification:notifications:publishown',
                    'notification:notifications:publishother',
                ], 'RETURN_ARRAY'),
                'security'    => $security,
                'entityViews' => $entityViews,
                'contacts'    => $this->forward(
                    'Mautic\NotificationBundle\Controller\NotificationController::contactsAction',
                    [
                        'objectId'   => $notification->getId(),
                        'page'       => $this->get('session')->get('mautic.notification.contact.page', 1),
                        'ignoreAjax' => true,
                    ]
                )->getContent(),
                'dateRangeForm' => $dateRangeForm->createView(),
            ],
            'contentTemplate' => 'MauticNotificationBundle:Notification:details.html.php',
            'passthroughVars' => [
                'activeLink'    => '#mautic_notification_index',
                'mauticContent' => 'notification',
            ],
        ]);
    }

    /**
     * Generates new form and processes post data.
     *
     * @param Notification $entity
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction($entity = null)
    {
        /** @var \Mautic\NotificationBundle\Model\NotificationModel $model */
        $model = $this->getModel('notification');

        if (!$entity instanceof Notification) {
            /** @var \Mautic\NotificationBundle\Entity\Notification $entity */
            $entity = $model->getEntity();
        }

        $method  = $this->request->getMethod();
        $session = $this->get('session');

        if (!$this->get('mautic.security')->isGranted('notification:notifications:create')) {
            return $this->accessDenied();
        }

        //set the page we came from
        $page         = $session->get('mautic.notification.page', 1);
        $action       = $this->generateUrl('mautic_notification_action', ['objectAction' => 'new']);
        $notification = $this->request->request->get('notification', []);
        $updateSelect = ('POST' == $method)
            ? ($notification['updateSelect'] ?? false)
            : $this->request->get('updateSelect', false);

        if ($updateSelect) {
            $entity->setNotificationType('template');
        }

        //create the form
        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if ('POST' === $method) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity);

                    $this->addFlash(
                        'mautic.core.notice.created',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_notification_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_notification_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if ($form->get('buttons')->get('save')->isClicked()) {
                        $viewParameters = [
                            'objectAction' => 'view',
                            'objectId'     => $entity->getId(),
                        ];
                        $returnUrl = $this->generateUrl('mautic_notification_action', $viewParameters);
                        $template  = 'Mautic\NotificationBundle\Controller\NotificationController::viewAction';
                    } else {
                        //return edit view so that all the session stuff is loaded
                        return $this->editAction($entity->getId(), true);
                    }
                }
            } else {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('mautic_notification_index', $viewParameters);
                $template       = 'Mautic\NotificationBundle\Controller\NotificationController::indexAction';
                //clear any modified content
                $session->remove('mautic.notification.'.$entity->getId().'.content');
            }

            $passthrough = [
                'activeLink'    => 'mautic_notification_index',
                'mauticContent' => 'notification',
            ];

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => $passthrough,
                    ]
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'         => $this->setFormTheme($form, 'MauticNotificationBundle:Notification:form.html.php', 'MauticNotificationBundle:FormTheme\Notification'),
                    'notification' => $entity,
                ],
                'contentTemplate' => 'MauticNotificationBundle:Notification:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_notification_index',
                    'mauticContent' => 'notification',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_notification_action',
                        [
                            'objectAction' => 'new',
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     * @param bool $forceTypeSelection
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction($objectId, $ignorePost = false, $forceTypeSelection = false)
    {
        /** @var \Mautic\NotificationBundle\Model\NotificationModel $model */
        $model   = $this->getModel('notification');
        $method  = $this->request->getMethod();
        $entity  = $model->getEntity($objectId);
        $session = $this->get('session');
        $page    = $session->get('mautic.notification.page', 1);

        //set the return URL
        $returnUrl = $this->generateUrl('mautic_notification_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\NotificationBundle\Controller\NotificationController::indexAction',
            'passthroughVars' => [
                'activeLink'    => 'mautic_notification_index',
                'mauticContent' => 'notification',
            ],
        ];

        //not found
        if (null === $entity) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.notification.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'notification:notifications:viewown',
            'notification:notifications:viewother',
            $entity->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'notification');
        }

        //Create the form
        $action       = $this->generateUrl('mautic_notification_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $notification = $this->request->request->get('notification', []);
        $updateSelect = 'POST' === $method
            ? ($notification['updateSelect'] ?? false)
            : $this->request->get('updateSelect', false);

        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if (!$ignorePost && 'POST' === $method) {
            $valid = false;

            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                    $this->addFlash(
                        'mautic.core.notice.updated',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_notification_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_notification_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ],
                        'warning'
                    );
                }
            } else {
                //clear any modified content
                $session->remove('mautic.notification.'.$objectId.'.content');
                //unlock the entity
                $model->unlockEntity($entity);
            }

            $template    = 'Mautic\NotificationBundle\Controller\NotificationController::viewAction';
            $passthrough = [
                'activeLink'    => 'mautic_notification_index',
                'mauticContent' => 'notification',
            ];

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                $viewParameters = [
                    'objectAction' => 'view',
                    'objectId'     => $entity->getId(),
                ];

                return $this->postActionRedirect(
                    array_merge(
                        $postActionVars,
                        [
                            'returnUrl'       => $this->generateUrl('mautic_notification_action', $viewParameters),
                            'viewParameters'  => $viewParameters,
                            'contentTemplate' => $template,
                            'passthroughVars' => $passthrough,
                        ]
                    )
                );
            }
        } else {
            //lock the entity
            $model->lockEntity($entity);
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'               => $this->setFormTheme($form, 'MauticNotificationBundle:Notification:form.html.php', 'MauticNotificationBundle:FormTheme\Notification'),
                    'notification'       => $entity,
                    'forceTypeSelection' => $forceTypeSelection,
                ],
                'contentTemplate' => 'MauticNotificationBundle:Notification:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_notification_index',
                    'mauticContent' => 'notification',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_notification_action',
                        [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Clone an entity.
     *
     * @param $objectId
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction($objectId)
    {
        $model  = $this->getModel('notification');
        $entity = $model->getEntity($objectId);

        if (null != $entity) {
            if (!$this->get('mautic.security')->isGranted('notification:notifications:create')
                || !$this->get('mautic.security')->hasEntityAccess(
                    'notification:notifications:viewown',
                    'notification:notifications:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $entity      = clone $entity;
            $session     = $this->get('session');
            $contentName = 'mautic.notification.'.$entity->getId().'.content';

            $session->set($contentName, $entity->getContent());
        }

        return $this->newAction($entity);
    }

    /**
     * Deletes the entity.
     *
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId)
    {
        $page      = $this->get('session')->get('mautic.notification.page', 1);
        $returnUrl = $this->generateUrl('mautic_notification_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\NotificationBundle\Controller\NotificationController::indexAction',
            'passthroughVars' => [
                'activeLink'    => 'mautic_notification_index',
                'mauticContent' => 'notification',
            ],
        ];

        if ('POST' == $this->request->getMethod()) {
            $model  = $this->getModel('notification');
            $entity = $model->getEntity($objectId);

            if (null === $entity) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.notification.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
                'notification:notifications:deleteown',
                'notification:notifications:deleteother',
                $entity->getCreatedBy()
            )
            ) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'notification');
            }

            $model->deleteEntity($entity);

            $flashes[] = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $entity->getName(),
                    '%id%'   => $objectId,
                ],
            ];
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        $page      = $this->get('session')->get('mautic.notification.page', 1);
        $returnUrl = $this->generateUrl('mautic_notification_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\NotificationBundle\Controller\NotificationController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_notification_index',
                'mauticContent' => 'notification',
            ],
        ];

        if ('POST' == $this->request->getMethod()) {
            $model = $this->getModel('notification');
            $ids   = json_decode($this->request->query->get('ids', '{}'));

            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if (null === $entity) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.notification.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->get('mautic.security')->hasEntityAccess(
                    'notification:notifications:viewown',
                    'notification:notifications:viewother',
                    $entity->getCreatedBy()
                )
                ) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'notification', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.notification.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    /**
     * @param $objectId
     *
     * @return JsonResponse|Response
     */
    public function previewAction($objectId)
    {
        /** @var \Mautic\NotificationBundle\Model\NotificationModel $model */
        $model        = $this->getModel('notification');
        $notification = $model->getEntity($objectId);

        return $this->delegateView(
            [
                'viewParameters' => [
                    'notification' => $notification,
                ],
                'contentTemplate' => 'MauticNotificationBundle:Notification:preview.html.php',
            ]
        );
    }

    /**
     * @param     $objectId
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function contactsAction($objectId, $page = 1)
    {
        return $this->generateContactsGrid(
            $objectId,
            $page,
            'notification:notifications:view',
            'notification',
            'push_notification_stats',
            'notification',
            'notification_id'
        );
    }
}
