<?php

declare(strict_types=1);

namespace Mautic\ProjectBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Mautic\ProjectBundle\Entity\Project;

class ProjectEntityLoaderService
{
    private EntityManagerInterface $em;
    private static array $entityTypesCache = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getProjectEntities(Project $project): array
    {
        $results     = [];
        $entityTypes = $this->getEntityTypes();

        foreach ($entityTypes as $entityType => $config) {
            // Načítaj entity pre tento projekt
            $repository = $this->em->getRepository($config['entityClass']);
            $entities   = $repository->createQueryBuilder('e')
                ->join('e.projects', 'p')
                ->where('p.id = :projectId')
                ->setParameter('projectId', $project->getId())
                ->getQuery()
                ->getResult();

            $results[$entityType] = [
                'label'    => $config['label'],
                'entities' => $entities,
                'count'    => count($entities),
            ];
        }

        return $results;
    }

    private function getEntityTypes(): array
    {
        if (!empty(self::$entityTypesCache)) {
            return self::$entityTypesCache;
        }

        $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();

        foreach ($allMetadata as $metadata) {
            $entityClass = $metadata->getName();

            // Nájdi many-to-many associáciu s Project
            foreach ($metadata->getAssociationMappings() as $association) {
                if (ClassMetadataInfo::MANY_TO_MANY === $association['type']
                    && Project::class === $association['targetEntity']) {
                    $shortName  = $metadata->getReflectionClass()->getShortName();
                    $entityType = strtolower($shortName);

                    self::$entityTypesCache[$entityType] = [
                        'entityClass' => $entityClass,
                        'label'       => $this->getEntityLabel($entityClass),
                    ];

                    break;
                }
            }
        }

        return self::$entityTypesCache;
    }

    private function getEntityLabel(string $entityClass): string
    {
        // Použij metadata namiesto nového reflection
        $metadata  = $this->em->getClassMetadata($entityClass);
        $shortName = strtolower($metadata->getReflectionClass()->getShortName());

        // Vráť translation key, nech sa Twig postará o preklad
        return "mautic.{$shortName}.{$shortName}s";
    }
}
