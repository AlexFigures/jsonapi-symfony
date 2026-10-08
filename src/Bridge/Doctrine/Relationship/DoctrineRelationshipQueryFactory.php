<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Relationship;

use AlexFigures\JsonApi\Bridge\Doctrine\Identifier\IdentifierParameters;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @internal Expresses membership without initializing an owner's collection. */
final readonly class DoctrineRelationshipQueryFactory
{
    public function __construct(private ManagerRegistry $managers, private ResourceRegistryInterface $resources)
    {
    }

    /** @param list<string> $owners
     * @return array{ResourceMetadata, Criteria}|null
     */
    public function select(string $type, string $relationship, array $owners, Criteria $criteria): ?array
    {
        $source = $this->resources->getByType($type);
        $edge = $source->relationships[$relationship] ?? null;
        $em = $this->managers->getManagerForClass($source->dataClass);
        if ($edge === null || !$em instanceof EntityManagerInterface) {
            return null;
        }
        $path = $edge->aliasPath ?? $edge->propertyPath ?? $edge->name;
        $class = $em->getClassMetadata($source->dataClass);
        /** @var array<string, string|\UnitEnum> $orderBy */
        $orderBy = [];
        foreach (explode('.', $path) as $segment) {
            if (!$class->hasAssociation($segment)) {
                return null;
            }
            $mapping = $class->getAssociationMapping($segment);
            /** @var array<string, string|\UnitEnum> $orderBy */
            $orderBy = $mapping['orderBy'] ?? [];
            $class = $em->getClassMetadata($class->getAssociationTargetClass($segment));
        }
        $target = $edge->targetType !== null ? $this->resources->getByType($edge->targetType) : $this->resources->getByClass($class->getName());
        if ($target === null || $target->dataClass !== $class->getName() || $this->managers->getManagerForClass($target->dataClass) !== $em) {
            return null;
        }
        $selected = clone $criteria;
        if ($selected->sort === []) {
            foreach ($orderBy as $field => $direction) {
                $descending = $direction instanceof \UnitEnum ? $direction->name !== 'Ascending' : strtoupper($direction) === 'DESC';
                $selected->sort[] = new \AlexFigures\JsonApi\Query\Sorting($field, $descending);
            }
        }
        $selected->customConditions[] = static function (QueryBuilder $query) use ($source, $path, $owners, $class, $em): void {
            $parameter = 'jsonapi_membership_' . count($query->getParameters());
            $sub = $em->createQueryBuilder()->from($source->dataClass, 'membership_source');
            $alias = 'membership_source';
            foreach (explode('.', $path) as $index => $segment) {
                $next = 'membership_target_' . $index;
                $sub->innerJoin($alias . '.' . $segment, $next);
                $alias = $next;
            }
            $sourceField = $em->getClassMetadata($source->dataClass)->getSingleIdentifierFieldName();
            $targetField = $class->getSingleIdentifierFieldName();
            $root = $query->getRootAliases()[0];
            $sub->select($alias . '.' . $targetField)
                ->where('membership_source.' . $sourceField . ' IN (:' . $parameter . ')');
            $query->andWhere($root . '.' . $targetField . ' IN (' . $sub->getDQL() . ')');
            IdentifierParameters::bind($query, $em, $source->dataClass, $parameter, $owners);
        };
        return [$target, $selected];
    }
}
