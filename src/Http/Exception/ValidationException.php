<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use JsonApi\Symfony\Http\Error\ErrorObject;

/**
 * Исключение для ошибок валидации.
 * 
 * HTTP статус: 422 Unprocessable Entity
 * 
 * Содержит массив JSON:API error objects с деталями валидации.
 */
final class ValidationException extends JsonApiHttpException
{
    /**
     * @param array<ErrorObject> $errors
     * @param array<string, string> $headers
     */
    public function __construct(array $errors, string $message = 'Validation failed', array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct(422, $message, $headers, $errors, $previous);
    }
}
