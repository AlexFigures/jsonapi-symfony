<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Error;

final class ErrorSource
{
    public function __construct(
        public readonly ?string $pointer = null,
        public readonly ?string $parameter = null,
        public readonly ?string $header = null,
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
