<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad Request')
    {
        parent::__construct(400, $message);
    }
}
