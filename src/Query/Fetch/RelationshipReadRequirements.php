<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Query\Fetch;

/** A batch reader must bound identifier/model fetches before hydration; null budgets mean unlimited, zero means exhausted. */
final readonly class RelationshipReadRequirements
{
    /** @param list<string> $ownerIds */
    public function __construct(
        public string $ownerType,
        public string $relationship,
        public string $targetType,
        public array $ownerIds,
        public bool $linkage,
        public bool $include,
        public bool $count,
        public ?int $remainingIdentifiers,
        public ?int $remainingIncluded,
        /** @var list<string> Already primary/reserved target identities; do not charge them twice. */
        public array $knownTargetIds = [],
    ) {
    }
}
