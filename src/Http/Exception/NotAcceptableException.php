<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Exception;

use AlexFigures\JsonApi\Http\Error\ErrorObject;

/** @api */
final class NotAcceptableException extends JsonApiHttpException
{
    /**
     * @param list<ErrorObject>     $errors
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly ?string $acceptHeader = null,
        string $message = 'Not Acceptable',
        array $errors = [],
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(406, $message, $headers, $errors, $previous);
    }

    public function getAcceptHeader(): ?string
    {
        return $this->acceptHeader;
    }
}
