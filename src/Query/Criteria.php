<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Query;

use JsonApi\Symfony\Filter\Ast\Node;

final class Criteria
{
    /**
     * @var array<string, list<string>>
     */
    public array $fields = [];

    /**
     * @var list<string>
     */
    public array $include = [];

    /**
     * @var list<Sorting>
     */
    public array $sort = [];

    /**
     * Filter AST (Abstract Syntax Tree) representing the filter query.
     *
     * @var Node|null
     */
    public ?Node $filter = null;

    public Pagination $pagination;

    /**
     * Custom QueryBuilder conditions for advanced filtering.
     *
     * These are callbacks that receive a QueryBuilder and can apply
     * custom conditions that can't be expressed through the standard
     * filter AST (e.g., subqueries, complex joins, OR conditions).
     *
     * @var list<callable(\Doctrine\ORM\QueryBuilder): void>
     */
    public array $customConditions = [];

    public function __construct(?Pagination $pagination = null)
    {
        $this->pagination = $pagination ?? new Pagination(1, 10);
    }
}
