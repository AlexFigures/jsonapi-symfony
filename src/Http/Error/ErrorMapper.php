<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Error;

use Throwable;

final class ErrorMapper
{
    public function __construct(
        private readonly ErrorBuilder $builder,
    ) {
    }

    public function invalidJson(Throwable $error): ErrorObject
    {
        return $this->builder->fromPointer(
            status: '400',
            code: ErrorCodes::INVALID_JSON,
            title: null,
            detail: $error->getMessage(),
            pointer: '/',
        );
    }

    public function invalidContentType(?string $got): ErrorObject
    {
        return $this->builder->fromHeader(
            status: '415',
            code: ErrorCodes::UNSUPPORTED_MEDIA_TYPE,
            title: null,
            detail: $got === null ? 'The Content-Type header must be present and set to application/vnd.api+json.' : sprintf('The Content-Type "%s" is not supported. Expected application/vnd.api+json.', $got),
            header: 'Content-Type',
        );
    }

    public function notAcceptable(?string $got): ErrorObject
    {
        return $this->builder->fromHeader(
            status: '406',
            code: ErrorCodes::NOT_ACCEPTABLE,
            title: null,
            detail: $got === null ? 'The Accept header must allow application/vnd.api+json.' : sprintf('The Accept header "%s" does not allow application/vnd.api+json.', $got),
            header: 'Accept',
        );
    }

    public function invalidParameter(string $parameter, string $detail, string $status = '400', string $code = ErrorCodes::INVALID_PARAMETER): ErrorObject
    {
        return $this->builder->fromParameter($status, $code, null, $detail, $parameter);
    }

    public function invalidPointer(string $pointer, string $detail, string $status = '400', string $code = ErrorCodes::INVALID_POINTER): ErrorObject
    {
        return $this->builder->fromPointer($status, $code, null, $detail, $pointer);
    }

    public function unknownType(string $type): ErrorObject
    {
        return $this->builder->create(
            status: '404',
            code: ErrorCodes::UNKNOWN_TYPE,
            title: null,
            detail: sprintf('Resource type "%s" is not recognized.', $type),
        );
    }

    public function unknownField(string $type, string $field): ErrorObject
    {
        return $this->invalidParameter(
            sprintf('fields[%s]', $type),
            sprintf('Field "%s" is not allowed for resource type "%s".', $field, $type),
            '400',
            ErrorCodes::UNKNOWN_FIELD,
        );
    }

    public function unknownAttribute(string $type, string $attribute): ErrorObject
    {
        return $this->invalidPointer(
            sprintf('/data/attributes/%s', $attribute),
            sprintf('Attribute "%s" is not allowed for resource type "%s".', $attribute, $type),
            '400',
            ErrorCodes::UNKNOWN_FIELD,
        );
    }

    public function unknownRelationship(string $type, string $relationship, string $status = '404'): ErrorObject
    {
        return $this->builder->fromPointer(
            $status,
            ErrorCodes::UNKNOWN_RELATIONSHIP,
            null,
            sprintf('Relationship "%s" is not defined for resource "%s".', $relationship, $type),
            sprintf('/data/relationships/%s', $relationship),
        );
    }

    public function pageSizeTooLarge(int $max): ErrorObject
    {
        return $this->invalidParameter(
            'page[size]',
            sprintf('Page size cannot be greater than %d.', $max),
            '400',
            ErrorCodes::PAGE_SIZE_TOO_LARGE,
        );
    }

    public function sortFieldNotAllowed(string $type, string $field): ErrorObject
    {
        return $this->invalidParameter(
            'sort',
            sprintf('Sorting by "%s" is not allowed for resource type "%s".', $field, $type),
            '400',
            ErrorCodes::SORT_FIELD_NOT_ALLOWED,
        );
    }

    public function typeMismatch(string $expected, string $actual): ErrorObject
    {
        return $this->builder->fromPointer(
            '409',
            ErrorCodes::TYPE_MISMATCH,
            null,
            sprintf('Resource type must be "%s", got "%s".', $expected, $actual),
            '/data/type',
        );
    }

    public function idMismatch(string $expected, ?string $actual): ErrorObject
    {
        $detail = $actual === null
            ? sprintf('Resource id must be "%s".', $expected)
            : sprintf('Resource id must match "%s", got "%s".', $expected, $actual);

        return $this->builder->fromPointer('409', ErrorCodes::ID_MISMATCH, null, $detail, '/data/id');
    }

    public function conflict(string $detail, ?string $pointer = null): ErrorObject
    {
        if ($pointer !== null) {
            return $this->builder->fromPointer('409', ErrorCodes::CONFLICT, null, $detail, $pointer);
        }

        return $this->builder->create('409', ErrorCodes::CONFLICT, null, $detail);
    }

    public function notFound(string $detail, ?string $pointer = null): ErrorObject
    {
        if ($pointer !== null) {
            return $this->builder->fromPointer('404', ErrorCodes::RESOURCE_NOT_FOUND, null, $detail, $pointer);
        }

        return $this->builder->create('404', ErrorCodes::RESOURCE_NOT_FOUND, null, $detail);
    }

    public function forbidden(string $detail): ErrorObject
    {
        return $this->builder->create('403', ErrorCodes::FORBIDDEN, null, $detail);
    }

    public function methodNotAllowed(array $allowed): ErrorObject
    {
        return $this->builder->create(
            '405',
            ErrorCodes::METHOD_NOT_ALLOWED,
            null,
            sprintf('Allowed methods: %s.', implode(', ', $allowed)),
        );
    }

    public function validationError(string $pointer, string $detail, array $meta = []): ErrorObject
    {
        return $this->builder->fromPointer('422', ErrorCodes::VALIDATION_ERROR, null, $detail, $pointer, $meta);
    }

    public function internal(string $detail = 'An unexpected error occurred.'): ErrorObject
    {
        return $this->builder->create('500', ErrorCodes::INTERNAL_SERVER_ERROR, null, $detail);
    }
}
