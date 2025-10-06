<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Relationship;

use InvalidArgumentException;

final class WriteRelationshipsResponseConfig
{
    /**
     * @param 'linkage'|'204' $mode
     */
    public function __construct(public string $mode = 'linkage')
    {
        if (!in_array($this->mode, ['linkage', '204'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported relationship write response mode "%s".', $this->mode));
        }
    }
}
