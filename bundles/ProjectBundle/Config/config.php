<?php

return [
    'routes' => [
        'main' => [
            'mautic_project_index' => [
                'path'       => '/projects/{page}',
                'controller' => 'Mautic\ProjectBundle\Controller\ProjectController::indexAction',
            ],
            'mautic_project_action' => [
                'path'       => '/projects/{objectAction}/{objectId}',
                'controller' => 'Mautic\ProjectBundle\Controller\ProjectController::executeAction',
            ],
        ],
        'validators' => [
            'project.unique_name.validator' => [
                'class'     => Mautic\ProjectBundle\Validator\Constraints\UniqueNameValidator::class,
                'arguments' => [
                    'mautic.project.repository.project',
                ],
                'tag'   => 'validator.constraint_validator',
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'project.menu.index' => [
                'id'        => Mautic\ProjectBundle\Controller\ProjectController::ROUTE_INDEX,
                'route'     => Mautic\ProjectBundle\Controller\ProjectController::ROUTE_INDEX,
                'access'    => Mautic\ProjectBundle\Security\Permissions\ProjectPermissions::CAN_VIEW,
                'iconClass' => 'ri-archive-stack-fill',
                'priority'  => 1,
            ],
        ],
    ],
];
