<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Concurrency;

use Doctrine\ORM\EntityManager;

/** Records unwanted enlistment and injects a second-manager commit fault. */
final class ObservedEntityManager extends EntityManager
{
    public int $begins = 0;
    public int $flushes = 0;
    public int $commits = 0;

    public function beginTransaction(): void
    {
        ++$this->begins;
        parent::beginTransaction();
    }

    public function flush(): void
    {
        ++$this->flushes;
        parent::flush();
    }

    public function commit(): void
    {
        ++$this->commits;
        throw new \RuntimeException('Injected unrelated manager commit failure.');
    }
}
