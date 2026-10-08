<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Authorization;

/** @api */
enum RelationshipOperation: string
{
    case READ_LINKAGE = 'read_linkage';
    case READ_RELATED = 'read_related';
    case REPLACE = 'replace';
    case ADD = 'add';
    case REMOVE = 'remove';
}
