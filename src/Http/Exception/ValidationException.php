<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Exception;

use AlexFigures\Symfony\Http\Error\ErrorObject;

/**
 * Exception for validation errors.
 *
 * HTTP status: 422 Unprocessable Entity
 *
 * Contains an array of JSON:API error objects with validation details.
 */
final class ValidationException extends JsonApiHttpException
{
    /**
     * @param array<ErrorObject>    $errors
     * @param array<string, string> $headers
     */
    public function __construct(array $errors, string $message = 'Validation failed', array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct(422, $message, $headers, $errors, $previous);
    }
}
