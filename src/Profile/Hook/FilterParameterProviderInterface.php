<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

/** Profile-owned filter flags consumed before parsing the resource filter AST.
 * @api
 */
interface FilterParameterProviderInterface
{
    /** @return list<string> */
    public function filterParameters(): array;
}
