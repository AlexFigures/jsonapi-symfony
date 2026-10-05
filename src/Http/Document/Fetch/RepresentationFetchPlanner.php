<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Document\Fetch;

use AlexFigures\Symfony\Profile\Hook\FetchPlanHookInterface;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;

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
        foreach ($context?->forType($metadata->type)->documentHooks() ?? [] as $hook) {
            if ($hook instanceof FetchPlanHookInterface) {
                $counts = array_merge($counts, $hook->relationshipCounts($metadata));
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
            $count = $visible && in_array($name, $counts, true);
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
