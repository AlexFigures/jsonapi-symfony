<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression;

/** In-memory fixture identities used by relationship endpoint validation. */
final class RcExistenceChecker implements \AlexFigures\JsonApi\Contract\Data\ExistenceChecker
{
    public function exists(string $type, string $id): bool
    {
        return in_array($type, ['rc-memory', 'rc-tagged'], true) && $id === 'stored';
    }
}
