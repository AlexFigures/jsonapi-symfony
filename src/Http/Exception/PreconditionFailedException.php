<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use JsonApi\Symfony\Http\Error\ErrorObject;

final class PreconditionFailedException extends JsonApiHttpException
{
    /**
     * @param list<ErrorObject>     $errors
     * @param array<string, string> $headers
     */
    public function __construct(array $errors, array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct(412, 'Precondition failed.', $headers, $errors, $previous);
    }
}
