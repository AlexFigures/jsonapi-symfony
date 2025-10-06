<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Exception;

use JsonApi\Symfony\Http\Error\ErrorObject;

final class UnsupportedMediaTypeException extends JsonApiHttpException
{
    /**
     * @param list<ErrorObject> $errors
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly ?string $mediaType = null,
        string $message = 'Unsupported Media Type',
        array $errors = [],
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(415, $message, $headers, $errors, $previous);
    }

    public function getMediaType(): ?string
    {
        return $this->mediaType;
    }
}
