<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Request;

use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;

final class SortingWhitelist
{
    public function __construct(
        private ResourceRegistryInterface $registry,
    ) {
    }

    /**
     * Returns the list of fields allowed for sorting for a given resource type.
     *
     * The sortable fields are defined using the #[SortableFields] attribute
     * on the entity class.
     *
     * This method returns only directly declared fields, not inherited ones.
     * Use isFieldAllowed() to check if a field is allowed (including inherited fields).
     *
     * @return list<string>
     */
    public function allowedFor(string $type): array
    {
        if (!$this->registry->hasType($type)) {
            return [];
        }

        $metadata = $this->registry->getByType($type);

        // Handle both old array format and new SortableFields object
        if (is_array($metadata->sortableFields)) {
            return $metadata->sortableFields;
        }

        return $metadata->sortableFields?->getAllowedFields() ?? [];
    }

    /**
     * Check if a field is allowed for sorting (including inherited fields).
     *
     * This method supports field inheritance from related resources when
     * a relationship is marked with inherit=true.
     *
     * @param string $type  Resource type
     * @param string $field Field name or path (e.g., 'title' or 'author.name')
     */
    public function isFieldAllowed(string $type, string $field): bool
    {
        if (!$this->registry->hasType($type)) {
            return false;
        }

        $metadata = $this->registry->getByType($type);

        // Handle old array format (backward compatibility)
        if (is_array($metadata->sortableFields)) {
            return in_array($field, $metadata->sortableFields, true);
        }

        // Use new SortableFields object with inheritance support
        return $metadata->sortableFields?->isAllowed($field, $this->registry, $type) ?? false;
    }
}
