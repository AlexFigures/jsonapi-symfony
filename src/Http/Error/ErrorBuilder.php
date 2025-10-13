<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Error;

final class ErrorBuilder
{
    public function __construct(
        private readonly bool $useDefaultTitleMap,
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
    ): ErrorObject {
        return $this->create($status, $code, $title, $detail, new ErrorSource(pointer: $pointer), $meta, $aboutLink);
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
    ): ErrorObject {
        return $this->create($status, $code, $title, $detail, new ErrorSource(parameter: $parameter), $meta, $aboutLink);
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
    ): ErrorObject {
        return $this->create($status, $code, $title, $detail, new ErrorSource(header: $header), $meta, $aboutLink);
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
