<?php

return [
    'routes' => [
        'main' => [
            'mautic_project_index' => [
                'path'       => '/projects/{page}',
                'controller' => 'MauticProjectBundle:Project:index',
            ],
            'mautic_project_action' => [
                'path'       => '/projects/{objectAction}/{objectId}',
                'controller' => 'MauticProjectBundle:Project:execute',
            ],
        ],
    ],
    'services' => [
        'controllers' => [
            'mautic.project.controller.project' => [
                'class'     => Mautic\ProjectBundle\Controller\ProjectController::class,
                'arguments' => [
                    'mautic.project.model.project',
                    'form.factory',
                    'mautic.security',
                ],
            ],
            'mautic.project.controller.ajax' => [
                'class'     => Mautic\ProjectBundle\Controller\AjaxController::class,
                'arguments' => [
                    'mautic.project.model.project',
                    'mautic.project.repository.project',
                    'mautic.security',
                ],
            ],
        ],
        'repositories' => [
            'mautic.project.repository.project' => [
                'class'     => Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    Mautic\ProjectBundle\Entity\Project::class,
                ],
            ],
        ],
        'models' => [
            'mautic.project.model.project' => [
                'class'     => Mautic\ProjectBundle\Model\ProjectModel::class,
                'arguments' => [
                    'service_container',
                ],
            ],
        ],
        'forms' => [
            'mautic.project.form.type.project' => [
                'class'     => Mautic\ProjectBundle\Form\Type\ProjectType::class,
                'arguments' => [
                    'translator',
                    'mautic.security',
                ],
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'project.menu.index' => [
                'id'        => Mautic\ProjectBundle\Controller\ProjectController::ROUTE_INDEX,
                'route'     => Mautic\ProjectBundle\Controller\ProjectController::ROUTE_INDEX,
                'access'    => Mautic\ProjectBundle\Security\Permissions\ProjectPermissions::CAN_VIEW,
                'iconClass' => 'fa-folder',
                'priority'  => 1,
            ],
        ],
    ],
];
