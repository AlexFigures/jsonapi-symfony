<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Error;

/** @api */
final readonly class ErrorSource
{
    public function __construct(
        public ?string $pointer = null,
        public ?string $parameter = null,
        public ?string $header = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->pointer !== null) {
            $data['pointer'] = $this->pointer;
        }

        if ($this->parameter !== null) {
            $data['parameter'] = $this->parameter;
        }

        if ($this->header !== null) {
            $data['header'] = $this->header;
        }

        return $data;
    }
}
