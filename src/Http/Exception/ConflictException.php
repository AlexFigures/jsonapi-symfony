<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict')
    {
        parent::__construct(409, $message);
    }
}
