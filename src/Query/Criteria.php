<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Query;

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

    public Pagination $pagination;

    public function __construct(?Pagination $pagination = null)
    {
        $this->pagination = $pagination ?? new Pagination(1, 10);
    }
}
