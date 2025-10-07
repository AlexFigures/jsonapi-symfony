<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Исключение для функционала, который не реализован.
 * 
 * HTTP статус: 501 Not Implemented
 */
final class NotImplementedException extends HttpException
{
    public function __construct(string $message = 'This functionality is not implemented.')
    {
        parent::__construct(501, $message);
    }
}

