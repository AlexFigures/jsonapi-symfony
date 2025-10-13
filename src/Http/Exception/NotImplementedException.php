<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception for functionality that is not implemented.
 *
 * HTTP status: 501 Not Implemented
 */
final class NotImplementedException extends HttpException
{
    public function __construct(string $message = 'This functionality is not implemented.')
    {
        parent::__construct(501, $message);
    }
}
