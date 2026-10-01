<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic\Execution;

use AlexFigures\Symfony\Atomic\Operation;

/** @internal Preflight roots and explicit relationship identifiers, including lids. */
final class AtomicResourceTypes
{
    /** @param list<Operation> $operations
     * @return list<string>
     */
    public static function collect(array $operations): array
    {
        $types = [];
        foreach ($operations as $operation) {
            if ($operation->ref !== null) {
                $types[] = $operation->ref->type;
            }
            if (!is_array($operation->data)) {
                continue;
            }
            if ($operation->isRelationshipOperation()) {
                self::collectLinkage($operation->data, $types);
                continue;
            }
            self::collectLinkage($operation->data, $types);
            $relationships = $operation->data['relationships'] ?? [];
            if (is_array($relationships)) {
                foreach ($relationships as $relationship) {
                    if (is_array($relationship) && is_array($relationship['data'] ?? null)) {
                        self::collectLinkage($relationship['data'], $types);
                    }
                }
            }
        }

        return array_values(array_unique($types));
    }

    /** @param array<array-key, mixed> $data
     * @param list<string> $types
     */
    private static function collectLinkage(array $data, array &$types): void
    {
        if (is_string($data['type'] ?? null)) {
            $types[] = $data['type'];
        } elseif (array_is_list($data)) {
            foreach ($data as $identifier) {
                if (is_array($identifier) && is_string($identifier['type'] ?? null)) {
                    $types[] = $identifier['type'];
                }
            }
        }
    }
}
