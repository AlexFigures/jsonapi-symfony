<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Builtin\Hook;

use AlexFigures\JsonApi\Profile\Attribute\SoftDeletable;
use AlexFigures\JsonApi\Profile\AttributeReader;
use AlexFigures\JsonApi\Profile\Hook\QueryHook;
use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Query\Criteria;
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
 *     field?: string, strategy?: string, default_visibility?: string, query_flags?: array{with_deleted?: string, only_deleted?: string},
 *     deletedAtField?: string,
 *     withTrashedParam?: string,
 *     onlyTrashedParam?: string,
 *     ...
 * }
 * @internal
 */
final readonly class SoftDeleteQueryHook implements QueryHook, \AlexFigures\JsonApi\Profile\Hook\FilterParameterProviderInterface
{
    /**
     * @param SoftDeleteQueryConfig $config
     */
    public function __construct(
        private array $config = []
    ) {
    }

    /** @return list<string> */
    public function filterParameters(): array
    {
        return [$this->config['query_flags']['with_deleted'] ?? $this->config['withTrashedParam'] ?? 'withTrashed', $this->config['query_flags']['only_deleted'] ?? $this->config['onlyTrashedParam'] ?? 'onlyTrashed'];
    }

    public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void
    {
        [$with, $only] = $this->filterParameters();
        $flags = $request->query->all('filter');
        foreach ([$with, $only] as $flag) {
            if (array_key_exists($flag, $flags) && !in_array($flags[$flag], [true, false, 1, 0, 'true', 'false', '1', '0'], true)) {
                throw new \AlexFigures\JsonApi\Http\Exception\BadRequestException('Soft-delete flags require a boolean value.', [new \AlexFigures\JsonApi\Http\Error\ErrorObject(id: null, aboutLink: null, status: '400', code: 'invalid-parameter', title: 'Invalid Parameter', detail: 'Soft-delete flags require a boolean value.', source: new \AlexFigures\JsonApi\Http\Error\ErrorSource(parameter: 'filter[' . $flag . ']'))]);
            }
        }
        $visibility = $this->config['default_visibility'] ?? 'exclude';
        if (in_array($flags[$only] ?? false, [true, 1, 'true', '1'], true)) {
            $visibility = 'only';
        } elseif (in_array($flags[$with] ?? false, [true, 1, 'true', '1'], true)) {
            $visibility = 'include';
        }
        if ($visibility === 'include') {
            return;
        }
        $attributeReader = $context->attributeReader();
        $configField = $this->config['field'] ?? $this->config['deletedAtField'] ?? 'deletedAt';
        $boolean = ($this->config['strategy'] ?? 'timestamp') === 'boolean';
        $deleted = $visibility === 'only';
        $criteria->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $qb) use ($attributeReader, $configField, $boolean, $deleted): void {
            $entityClass = $qb->getRootEntities()[0];
            $field = self::resolveDeletedAtField($attributeReader, $entityClass, $configField);
            $path = $qb->getRootAliases()[0] . '.' . $field;
            if ($boolean) {
                $parameter = 'jsonapi_soft_deleted_' . count($qb->getParameters());
                $qb->andWhere($path . ' = :' . $parameter)->setParameter($parameter, $deleted, \Doctrine\DBAL\Types\Types::BOOLEAN);
            } else {
                $qb->andWhere($deleted ? $qb->expr()->isNotNull($path) : $qb->expr()->isNull($path));
            }
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
        if ($attribute instanceof SoftDeletable && ($attribute->deletedAtField !== 'deletedAt' || $configField === 'deletedAt')) {
            return $attribute->deletedAtField;
        }

        // Fallback to config
        return $configField;
    }
}
