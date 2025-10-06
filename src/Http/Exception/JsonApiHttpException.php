<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use JsonApi\Symfony\Http\Error\ErrorObject;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class JsonApiHttpException extends RuntimeException implements HttpExceptionInterface
{
    /**
     * @param list<ErrorObject> $errors
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
        private readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message === '' ? (string) $statusCode : $message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return list<ErrorObject>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
