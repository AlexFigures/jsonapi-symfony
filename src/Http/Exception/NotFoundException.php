<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Exception;

use AlexFigures\Symfony\Http\Error\ErrorObject;

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
