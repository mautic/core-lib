<?php

declare(strict_types=1);

namespace Mautic\ApiBundle\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Custom processor for PUT operations to ensure entities are updated instead of created.
 * 
 * This processor decorates the default persist processor and intercepts PUT operations
 * to load existing entities from the database and merge incoming data, ensuring updates
 * rather than creation of new entities. It applies globally to all API Platform entities.
 */
final class PutProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Only handle PUT operations with an ID in the URI and valid entity data
        if (!$operation instanceof Put || !isset($uriVariables['id']) || !is_object($data)) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $entityClass = get_class($data);
        $id = $uriVariables['id'];

        // Load the existing entity from the database
        $existingEntity = $this->entityManager->find($entityClass, $id);

        if (null === $existingEntity) {
            // Entity doesn't exist, let the default processor create it
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // For PUT operations, we want to update the existing entity
        // The incoming $data already contains the deserialized changes
        // We need to merge those changes into the existing entity
        
        $this->mergeEntityData($data, $existingEntity, $entityClass);

        // Make sure the entity is managed by Doctrine
        if (!$this->entityManager->contains($existingEntity)) {
            $existingEntity = $this->entityManager->merge($existingEntity);
        }

        // Persist the changes
        $this->entityManager->persist($existingEntity);
        $this->entityManager->flush();

        return $existingEntity;
    }

    /**
     * Merge data from the incoming entity into the existing entity.
     */
    private function mergeEntityData(object $sourceEntity, object $targetEntity, string $entityClass): void
    {
        // Get the entity metadata to know which properties to update
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        
        // Update regular fields
        foreach ($metadata->getFieldNames() as $fieldName) {
            if (!$metadata->isIdentifier($fieldName)) {
                $this->updateEntityField($sourceEntity, $targetEntity, $fieldName);
            }
        }

        // Update associations
        foreach ($metadata->getAssociationNames() as $associationName) {
            $this->updateEntityField($sourceEntity, $targetEntity, $associationName);
        }
    }

    /**
     * Update a single field/association on the target entity from the source entity.
     */
    private function updateEntityField(object $sourceEntity, object $targetEntity, string $fieldName): void
    {
        $getter = 'get' . ucfirst($fieldName);
        $setter = 'set' . ucfirst($fieldName);
        
        if (method_exists($sourceEntity, $getter) && method_exists($targetEntity, $setter)) {
            $value = $sourceEntity->$getter();
            // Only update if the incoming data has a value (not null)
            if (null !== $value) {
                $targetEntity->$setter($value);
            }
        }
    }
}