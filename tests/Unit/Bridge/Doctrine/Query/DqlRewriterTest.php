<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Bridge\Doctrine\Query;

use AlexFigures\JsonApi\Bridge\Doctrine\Query\DqlRewriter;
use PHPUnit\Framework\TestCase;

final class DqlRewriterTest extends TestCase
{
    public function testRenamingDoesNotRewriteQuotedLiteralsOrSameNamedFields(): void
    {
        $dql = "SELECT e.e FROM App\\Entity e WHERE e.title = ':search e.e' AND e.id = :search AND e.id = :search_long AND e.e = ?1";
        self::assertSame("SELECT leaf.e FROM App\\Entity leaf WHERE leaf.title = ':search e.e' AND leaf.id = :branch_search AND leaf.id = :branch_search_long AND leaf.e = :branch_1", DqlRewriter::rewrite($dql, ['e' => 'leaf'], ['search' => 'branch_search', 'search_long' => 'branch_search_long', '1' => 'branch_1']));
    }
}
