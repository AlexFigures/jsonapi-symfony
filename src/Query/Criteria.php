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

    public function __construct(?Pagination $pagination = null)
    {
        $this->pagination = $pagination ?? new Pagination(1, 10);
    }
}
