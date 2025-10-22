<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin\Hook;

use AlexFigures\Symfony\Profile\Hook\DocumentHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Document hook for relationship counts profile.
 *
 * Adds count metadata to relationship objects in JSON:API documents.
 *
 * Usage:
 * - Adds "meta": {"count": N} to each to-many relationship
 * - Works with Doctrine collections (counts without loading all items)
 * - Configurable to include/exclude specific relationships
 *
 * Example output:
 * {
 *   "data": {
 *     "type": "articles",
 *     "id": "1",
 *     "relationships": {
 *       "comments": {
 *         "data": [...],
 *         "meta": {"count": 42}
 *       }
 *     }
 *   }
 * }
 *
 * @phpstan-type RelationshipCountsConfig array{
 *     includeRelationships?: list<string>,
 *     excludeRelationships?: list<string>,
 *     propertyAccessor?: PropertyAccessorInterface
 * }
 */
final readonly class RelationshipCountsDocumentHook implements DocumentHook
{
    private PropertyAccessorInterface $propertyAccessor;

    /**
     * @param RelationshipCountsConfig $config
     */
    public function __construct(
        private array $config = []
    ) {
        $this->propertyAccessor = $config['propertyAccessor'] ?? PropertyAccess::createPropertyAccessor();
    }

    public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void
    {
        // No top-level links modifications needed
    }

    public function onResourceRelationships(
        ProfileContext $context,
        ResourceMetadata $metadata,
        array &$relationshipsPayload,
        object $model
    ): void {
        $includeList = $this->config['includeRelationships'] ?? null;
        $excludeList = $this->config['excludeRelationships'] ?? [];

        foreach ($relationshipsPayload as $relationshipName => &$relationshipData) {
            // Skip if not in include list (when include list is specified)
            if ($includeList !== null && !in_array($relationshipName, $includeList, true)) {
                continue;
            }

            // Skip if in exclude list
            if (in_array($relationshipName, $excludeList, true)) {
                continue;
            }

            // Try to get the relationship value from the model
            try {
                if (!$this->propertyAccessor->isReadable($model, $relationshipName)) {
                    continue;
                }

                $value = $this->propertyAccessor->getValue($model, $relationshipName);

                // Only add count for to-many relationships (collections)
                if ($value instanceof Collection) {
                    // Use count() which is efficient for Doctrine collections
                    // (doesn't load all items if not already loaded)
                    $existingMeta = $relationshipData['meta'] ?? [];
                    if (!is_array($existingMeta)) {
                        $existingMeta = [];
                    }
                    $relationshipData['meta'] = array_merge(
                        $existingMeta,
                        ['count' => $value->count()]
                    );
                }
            } catch (\Throwable) {
                // Silently skip if we can't access the relationship
                continue;
            }
        }
    }

    public function onTopLevelMeta(ProfileContext $context, array &$meta): void
    {
        // No top-level meta modifications needed
    }
}
