<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

/** Profile-owned filter flags consumed before parsing the resource filter AST. */
interface FilterParameterProviderInterface
{
    /** @return list<string> */
    public function filterParameters(): array;
}
