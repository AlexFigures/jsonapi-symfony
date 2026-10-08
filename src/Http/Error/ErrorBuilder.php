<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Error;

/** @api */
final readonly class ErrorBuilder
{
    public function __construct(
        private bool $useDefaultTitleMap,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function create(
        string $status,
        string $code,
        ?string $title = null,
        ?string $detail = null,
        ?ErrorSource $source = null,
        array $meta = [],
        ?string $aboutLink = null,
        ?string $typeLink = null,
    ): ErrorObject {
        return new ErrorObject(
            id: null,
            aboutLink: $aboutLink,
            status: $status,
            code: $code,
            title: $this->resolveTitle($title, $code),
            detail: $detail,
            source: $source,
            meta: $meta,
            typeLink: $typeLink,
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function fromPointer(
        string $status,
        string $code,
        ?string $title,
        ?string $detail,
        string $pointer,
        array $meta = [],
        ?string $aboutLink = null,
        ?string $typeLink = null,
    ): ErrorObject {
        return $this->create($status, $code, $title, $detail, new ErrorSource(pointer: $pointer), $meta, $aboutLink, $typeLink);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function fromParameter(
        string $status,
        string $code,
        ?string $title,
        ?string $detail,
        string $parameter,
        array $meta = [],
        ?string $aboutLink = null,
        ?string $typeLink = null,
    ): ErrorObject {
        return $this->create($status, $code, $title, $detail, new ErrorSource(parameter: $parameter), $meta, $aboutLink, $typeLink);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function fromHeader(
        string $status,
        string $code,
        ?string $title,
        ?string $detail,
        string $header,
        array $meta = [],
        ?string $aboutLink = null,
        ?string $typeLink = null,
    ): ErrorObject {
        return $this->create($status, $code, $title, $detail, new ErrorSource(header: $header), $meta, $aboutLink, $typeLink);
    }

    private function resolveTitle(?string $title, string $code): ?string
    {
        if ($title !== null) {
            return $title;
        }

        if (!$this->useDefaultTitleMap) {
            return null;
        }

        return ErrorTitles::MAP[$code] ?? null;
    }
}
