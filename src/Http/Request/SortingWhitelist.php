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
     * @return list<string>
     */
    public function allowedFor(string $type): array
    {
        if (!$this->registry->hasType($type)) {
            return [];
        }

        $metadata = $this->registry->getByType($type);
        return $metadata->sortableFields;
    }
}
