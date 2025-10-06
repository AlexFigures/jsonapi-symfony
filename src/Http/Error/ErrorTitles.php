<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Error;

final class ErrorTitles
{
    /**
     * @var array<string, string>
     */
    public const MAP = [
        ErrorCodes::INVALID_JSON => 'Invalid JSON payload',
        ErrorCodes::UNSUPPORTED_MEDIA_TYPE => 'Unsupported media type',
        ErrorCodes::NOT_ACCEPTABLE => 'Not acceptable',
        ErrorCodes::INVALID_PARAMETER => 'Invalid query parameter',
        ErrorCodes::UNKNOWN_TYPE => 'Unknown resource type',
        ErrorCodes::UNKNOWN_FIELD => 'Unknown field',
        ErrorCodes::UNKNOWN_RELATIONSHIP => 'Unknown relationship',
        ErrorCodes::INVALID_POINTER => 'Invalid document member',
        ErrorCodes::TYPE_MISMATCH => 'Type mismatch',
        ErrorCodes::ID_MISMATCH => 'ID mismatch',
        ErrorCodes::CONFLICT => 'Conflict',
        ErrorCodes::FORBIDDEN => 'Forbidden',
        ErrorCodes::RESOURCE_NOT_FOUND => 'Resource not found',
        ErrorCodes::METHOD_NOT_ALLOWED => 'Method not allowed',
        ErrorCodes::VALIDATION_ERROR => 'Validation error',
        ErrorCodes::PAGE_SIZE_TOO_LARGE => 'Page size too large',
        ErrorCodes::SORT_FIELD_NOT_ALLOWED => 'Sort field not allowed',
        ErrorCodes::INTERNAL_SERVER_ERROR => 'Internal server error',
    ];
}
