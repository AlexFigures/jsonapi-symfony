<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Exception;

use AlexFigures\Symfony\Http\Error\ErrorObject;

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
