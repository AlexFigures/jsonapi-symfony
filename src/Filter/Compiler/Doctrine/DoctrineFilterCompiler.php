<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Compiler\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Filter\Ast\Node;
use JsonApi\Symfony\Filter\Operator\Registry;

/**
 * Compiles filter ASTs into Doctrine ORM QueryBuilder expressions.
 */
final class DoctrineFilterCompiler
{
    public function __construct(
        private readonly Registry $operators,
    ) {
    }

    public function apply(QueryBuilder $qb, Node $ast, AbstractPlatform $platform): void
    {
        // Proper compilation will be implemented in a future iteration. The
        // method signature is already in place so collaborators can rely on it.
    }
}
