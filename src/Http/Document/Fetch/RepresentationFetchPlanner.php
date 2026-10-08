<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Document\Fetch;

use AlexFigures\JsonApi\Profile\Hook\FetchPlanHookInterface;
use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;

/** @internal Pure representation requirements; filters/sorts/page select the roots upstream. */
final readonly class RepresentationFetchPlanner
{
    public function __construct(private string $linkageMode = 'when_included')
    {
    }

    /** @param array<string, mixed> $tree
     * @return list<RelationshipFetch>
     */
    public function edges(ResourceMetadata $metadata, Criteria $criteria, array $tree, ?ProfileContext $context): array
    {
        $counts = [];
        $hookReads = [];
        foreach ($context?->forType($metadata->type)->documentHooks() ?? [] as $hook) {
            if ($hook instanceof \AlexFigures\JsonApi\Profile\Hook\RelationshipFetchRequirementsHookInterface) {
                foreach ($hook->relationshipReads($metadata) as $name => $requirement) {
                    if (!isset($metadata->relationships[$name])) {
                        throw new \LogicException('Hook fetch plan refers to an unknown relationship: ' . $name);
                    }
                    $hookReads[$name][$requirement] = true;
                }
            }
            if ($hook instanceof FetchPlanHookInterface) {
                $typedContext = $context?->forType($metadata->type);
                $counts = array_merge($counts, $typedContext !== null && $hook instanceof \AlexFigures\JsonApi\Profile\Hook\ContextualFetchPlanHookInterface ? $hook->relationshipCountsForContext($metadata, $typedContext) : $hook->relationshipCounts($metadata));
            }
        }
        $fields = $criteria->fields[$metadata->type] ?? null;
        $edges = [];
        foreach ($metadata->relationships as $name => $relationship) {
            $visible = $fields === null || in_array($name, $fields, true);
            $requested = isset($tree[$name]) || ($fields !== null && in_array($name, $fields, true));
            foreach ($criteria->include as $path) {
                $requested = $requested || $path === $name || str_starts_with($path, $name . '.');
            }
            $linkage = $visible && match ($this->linkageMode) {
                'always' => true,
                'never' => isset($tree[$name]),
                default => $requested,
            };
            /** @var array<string, mixed>|null $children */
            $children = isset($tree[$name]) && is_array($tree[$name]) ? $tree[$name] : null;
            if (isset($hookReads[$name]['identifiers'])) {
                $linkage = true;
            }
            if (isset($hookReads[$name]['models'])) {
                $children ??= [];
            }
            $count = ($visible && in_array($name, $counts, true)) || isset($hookReads[$name]['count']);
            if ($linkage || $children !== null || $count) {
                $edges[] = new RelationshipFetch($relationship, $linkage, $children, $count);
            }
        }
        return $edges;
    }

    /** @return array<string, mixed> */
    public function includeTree(Criteria $criteria): array
    {
        $tree = [];
        foreach ($criteria->include as $path) {
            $cursor = &$tree;
            foreach (explode('.', $path) as $segment) {
                if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }
                /** @var array<string, mixed> $next */
                $next = &$cursor[$segment];
                $cursor = &$next;
            }
            unset($cursor);
        }
        return $tree;
    }
}
