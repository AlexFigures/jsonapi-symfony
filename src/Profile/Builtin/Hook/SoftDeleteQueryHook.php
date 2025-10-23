<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin\Hook;

use AlexFigures\Symfony\Profile\Attribute\SoftDeletable;
use AlexFigures\Symfony\Profile\AttributeReader;
use AlexFigures\Symfony\Profile\Hook\QueryHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use Symfony\Component\HttpFoundation\Request;

/**
 * Query hook for soft delete profile.
 *
 * Automatically filters out soft-deleted resources from queries unless
 * explicitly requested via query parameter.
 *
 * Usage:
 * - By default, adds filter to exclude soft-deleted items (deletedAt IS NULL)
 * - Use ?filter[withTrashed]=true to include soft-deleted items
 * - Use ?filter[onlyTrashed]=true to show only soft-deleted items
 *
 * @phpstan-type SoftDeleteQueryConfig array{
 *     deletedAtField?: string,
 *     withTrashedParam?: string,
 *     onlyTrashedParam?: string
 * }
 */
final readonly class SoftDeleteQueryHook implements QueryHook
{
    /**
     * @param SoftDeleteQueryConfig $config
     */
    public function __construct(
        private array $config = []
    ) {
    }

    public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void
    {
        $withTrashedParam = $this->config['withTrashedParam'] ?? 'withTrashed';
        $onlyTrashedParam = $this->config['onlyTrashedParam'] ?? 'onlyTrashed';

        // Check query parameters
        $filterParams = $request->query->all('filter');
        $withTrashed = $filterParams[$withTrashedParam] ?? false;
        $onlyTrashed = $filterParams[$onlyTrashedParam] ?? false;

        // If withTrashed is true, don't add any filter (show all)
        if ($withTrashed === 'true' || $withTrashed === '1' || $withTrashed === true) {
            return;
        }

        // Capture context and config for use in closure
        $attributeReader = $context->attributeReader();
        $configDeletedAtField = $this->config['deletedAtField'] ?? 'deletedAt';

        // If onlyTrashed is true, show only deleted items
        if ($onlyTrashed === 'true' || $onlyTrashed === '1' || $onlyTrashed === true) {
            $criteria->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $qb) use ($attributeReader, $configDeletedAtField): void {
                $entityClass = $qb->getRootEntities()[0];
                $deletedAtField = self::resolveDeletedAtField($attributeReader, $entityClass, $configDeletedAtField);
                $qb->andWhere($qb->expr()->isNotNull('e.' . $deletedAtField));
            };
            return;
        }

        // Default: exclude soft-deleted items
        $criteria->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $qb) use ($attributeReader, $configDeletedAtField): void {
            $entityClass = $qb->getRootEntities()[0];
            $deletedAtField = self::resolveDeletedAtField($attributeReader, $entityClass, $configDeletedAtField);
            $qb->andWhere($qb->expr()->isNull('e.' . $deletedAtField));
        };
    }

    /**
     * Resolve the deletedAt field name from attribute or config.
     *
     * @param class-string $entityClass
     */
    private static function resolveDeletedAtField(AttributeReader $attributeReader, string $entityClass, string $configField): string
    {
        // Try to read from attribute first
        $attribute = $attributeReader->getAttribute($entityClass, SoftDeletable::class);
        if ($attribute instanceof SoftDeletable) {
            return $attribute->deletedAtField;
        }

        // Fallback to config
        return $configField;
    }
}
