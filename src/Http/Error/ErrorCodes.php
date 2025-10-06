<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Error;

final class ErrorCodes
{
    public const INVALID_JSON = 'invalid-json';
    public const UNSUPPORTED_MEDIA_TYPE = 'unsupported-media-type';
    public const NOT_ACCEPTABLE = 'not-acceptable';
    public const INVALID_PARAMETER = 'invalid-parameter';
    public const UNKNOWN_TYPE = 'unknown-type';
    public const UNKNOWN_FIELD = 'unknown-field';
    public const UNKNOWN_RELATIONSHIP = 'unknown-relationship';
    public const INVALID_POINTER = 'invalid-pointer';
    public const TYPE_MISMATCH = 'type-mismatch';
    public const ID_MISMATCH = 'id-mismatch';
    public const CONFLICT = 'conflict';
    public const FORBIDDEN = 'forbidden';
    public const RESOURCE_NOT_FOUND = 'resource-not-found';
    public const METHOD_NOT_ALLOWED = 'method-not-allowed';
    public const VALIDATION_ERROR = 'validation-error';
    public const PAGE_SIZE_TOO_LARGE = 'page-size-too-large';
    public const SORT_FIELD_NOT_ALLOWED = 'sort-field-not-allowed';
    public const INTERNAL_SERVER_ERROR = 'internal-server-error';
}
