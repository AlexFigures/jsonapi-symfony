<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Error;

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
    public const FILTER_FIELD_NOT_ALLOWED = 'filter-field-not-allowed';
    public const FILTER_OPERATOR_NOT_ALLOWED = 'filter-operator-not-allowed';
    public const FILTER_NOT_ALLOWED = 'filter-not-allowed';
    public const INVALID_HEADER = 'invalid-header';
    public const PRECONDITION_FAILED = 'precondition-failed';
    public const PRECONDITION_REQUIRED = 'precondition-required';
    public const REQUEST_COMPLEXITY_EXCEEDED = 'request-complexity-exceeded';
    public const INCLUDED_RESOURCES_LIMIT = 'included-resources-limit';
    public const INTERNAL_SERVER_ERROR = 'internal-server-error';
}
