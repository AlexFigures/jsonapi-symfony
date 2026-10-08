<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Exception;

use AlexFigures\JsonApi\Http\Error\ErrorObject;

/** @api */
final class NotFoundException extends JsonApiHttpException
{
    /**
     * @param list<ErrorObject>     $errors
     * @param array<string, string> $headers
     */
    public function __construct(string $message = 'Not Found', array $errors = [], array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $headers, $errors, $previous);
    }
}
