<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Exception;

use AlexFigures\JsonApi\Http\Error\ErrorObject;

/** @api */
final class MultiErrorException extends JsonApiHttpException
{
    /**
     * @param list<ErrorObject>     $errors
     * @param array<string, string> $headers
     */
    public function __construct(int $status, array $errors, string $message = 'Request cannot be processed', array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct($status, $message, $headers, $errors, $previous);
    }
}
