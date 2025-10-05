<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found')
    {
        parent::__construct(404, $message);
    }
}
