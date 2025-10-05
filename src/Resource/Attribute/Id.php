<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Id
{
}
