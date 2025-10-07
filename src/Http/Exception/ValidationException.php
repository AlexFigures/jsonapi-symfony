<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use JsonApi\Symfony\Http\Error\ErrorObject;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Исключение для ошибок валидации.
 * 
 * HTTP статус: 422 Unprocessable Entity
 * 
 * Содержит массив JSON:API error objects с деталями валидации.
 */
final class ValidationException extends HttpException
{
    /**
     * @param array<ErrorObject> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct(422, $message);
    }

    /**
     * @return array<ErrorObject>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

