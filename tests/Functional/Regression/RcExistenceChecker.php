<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

/** In-memory fixture identities used by relationship endpoint validation. */
final class RcExistenceChecker implements \AlexFigures\Symfony\Contract\Data\ExistenceChecker
{
    public function exists(string $type, string $id): bool
    {
        return in_array($type, ['rc-memory', 'rc-tagged'], true) && $id === 'stored';
    }
}
