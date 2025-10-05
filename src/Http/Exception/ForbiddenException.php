<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct(403, $message);
    }
}
