<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Document\Fetch;

use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;

/** @internal One graph edge, independently loaded from sibling relationships. */
final readonly class RelationshipFetch
{
    /** @param array<string, mixed>|null $includeChildren */
    public function __construct(
        public RelationshipMetadata $relationship,
        public bool $linkage,
        public ?array $includeChildren,
        public bool $count,
    ) {
    }
}
