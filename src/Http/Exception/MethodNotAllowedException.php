<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Exception;

use AlexFigures\Symfony\Http\Error\ErrorObject;

final class MethodNotAllowedException extends JsonApiHttpException
{
    /**
     * @param list<string>      $allowedMethods
     * @param list<ErrorObject> $errors
     */
    public function __construct(
        private readonly array $allowedMethods,
        string $message = 'Method Not Allowed',
        array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            405,
            $message,
            ['Allow' => implode(', ', $allowedMethods)],
            $errors,
            $previous,
        );
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
