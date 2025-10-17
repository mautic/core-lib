<?php

declare(strict_types=1);

namespace Mautic\ProjectBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\ProjectBundle\Entity\Project;
use Mautic\ProjectBundle\Entity\ProjectRepository;
use Mautic\ProjectBundle\Model\ProjectModel;
use Mautic\ProjectBundle\Security\Permissions\ProjectPermissions;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    public function __construct(
        private ProjectModel $projectModel,
        private ProjectRepository $projectRepository,
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
    ) {
        parent::__construct(
            $doctrine,
            $modelFactory,
            $userHelper,
            $coreParametersHelper,
            $dispatcher,
            $translator,
            $flashBag,
            $requestStack,
            $security,
        );
    }

    public function getLookupChoiceListAction(Request $request, ProjectModel $projectModel): JsonResponse
    {
        $entityType  = $request->query->get('entityType');

        if (empty($entityType)) {
            return new JsonResponse([]);
        }

        $searchKey   = $request->query->get('searchKey', '');
        $searchValue = $request->query->get($searchKey, '');
        $filter      = $searchValue ?: $request->query->get('search', '');
        $limit       = (int) $request->query->get('limit', '10');
        $start       = (int) $request->query->get('start', '0');

        $results = $projectModel->getLookupResults($entityType, $filter, $limit, $start);

        // Format results to match AjaxLookupControllerTrait structure
        $dataArray = [];
        foreach ($results as $value => $text) {
            $dataArray[] = [
                'text'  => $text,
                'value' => $value,
            ];
        }

        return new JsonResponse($dataArray);
    }

    public function addProjectsAction(Request $request, ProjectModel $projectModel, ProjectRepository $projectRepository, CorePermissions $corePermissions): JsonResponse
    {
        if (!$corePermissions->isGranted(ProjectPermissions::CAN_ASSOCIATE)) {
            $this->accessDenied();
        }

        $existingProjectIds = json_decode($request->request->get('existingProjectIds'), true);
        $newProjectNames    = json_decode($request->request->get('newProjectNames'), true);

        if ($corePermissions->isGranted(ProjectPermissions::CAN_CREATE)) {
            foreach ($newProjectNames as $projectName) {
                $project = new Project();
                $project->setName($projectName);

                $existingProjectIds[] = $this->createProjectIfNotExists(trim($projectName));
            }
        }

        // Get an updated list of projects
        $allProjects    = $projectRepository->getSimpleList(null, [], 'name');
        $projectOptions = '';

        foreach ($allProjects as $project) {
            $selected = in_array($project['value'], $existingProjectIds) ? ' selected="selected"' : '';
            $projectOptions .= '<option'.$selected.' value="'.$project['value'].'">'.$project['label'].'</option>';
        }

        return $this->sendJsonResponse(['projects' => $projectOptions]);
    }

    private function createProjectIfNotExists(string $name): int
    {
        if ($project = $this->projectRepository->findOneBy(['name' => $name])) {
            return $project->getId();
        }

        $project = new Project();
        $project->setName($name);
        $this->projectModel->saveEntity($project);

        return $project->getId();
    }
}
