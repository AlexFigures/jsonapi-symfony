<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Relationship;

use Doctrine\ORM\EntityManagerInterface;

/** @internal */
final class RelationshipNullability
{
    public static function allowsNull(EntityManagerInterface $em, object $entity, string $property, bool $fallback): bool
    {
        $metadata = $em->getClassMetadata($entity::class);
        if (!$metadata->hasAssociation($property)) {
            return $fallback;
        }
        $mapping = $metadata->getAssociationMapping($property);
        if (isset($mapping->joinColumns)) {
            foreach ($mapping->joinColumns as $column) {
                if ($column->nullable === false) {
                    return false;
                }
            }
        }
        return $fallback;
    }
}
