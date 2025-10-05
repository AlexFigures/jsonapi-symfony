<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class JsonApiHttpException extends HttpException
{
    public static function notAcceptable(string $message = 'Not Acceptable'): self
    {
        return new self(406, $message);
    }

    public static function unsupportedMediaType(string $message = 'Unsupported Media Type'): self
    {
        return new self(415, $message);
    }
}
