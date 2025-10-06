<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use JsonApi\Symfony\Http\Error\ErrorObject;

final class NotFoundException extends JsonApiHttpException
{
    /**
     * @param list<ErrorObject> $errors
     * @param array<string, string> $headers
     */
    public function __construct(string $message = 'Not Found', array $errors = [], array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $headers, $errors, $previous);
    }
}
