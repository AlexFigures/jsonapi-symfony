<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use JsonApi\Symfony\Http\Error\ErrorObject;

final class ForbiddenException extends JsonApiHttpException
{
    /**
     * @param list<ErrorObject>     $errors
     * @param array<string, string> $headers
     */
    public function __construct(string $message = 'Forbidden', array $errors = [], array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $headers, $errors, $previous);
    }
}
