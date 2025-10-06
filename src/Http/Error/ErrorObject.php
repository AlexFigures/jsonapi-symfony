<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Error;

final class ErrorObject
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $aboutLink,
        public readonly string $status,
        public readonly string $code,
        public readonly ?string $title,
        public readonly ?string $detail,
        public readonly ?ErrorSource $source,
        public readonly array $meta = [],
    ) {
    }

    public function withId(?string $id): self
    {
        if ($id === $this->id) {
            return $this;
        }

        return new self($id, $this->aboutLink, $this->status, $this->code, $this->title, $this->detail, $this->source, $this->meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function withMergedMeta(array $meta): self
    {
        if ($meta === []) {
            return $this;
        }

        return new self(
            $this->id,
            $this->aboutLink,
            $this->status,
            $this->code,
            $this->title,
            $this->detail,
            $this->source,
            array_replace($this->meta, $meta),
        );
    }

    public function withAboutLink(?string $link): self
    {
        if ($link === $this->aboutLink) {
            return $this;
        }

        return new self($this->id, $link, $this->status, $this->code, $this->title, $this->detail, $this->source, $this->meta);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
            'code' => $this->code,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->aboutLink !== null) {
            $data['links'] = ['about' => $this->aboutLink];
        }

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->detail !== null) {
            $data['detail'] = $this->detail;
        }

        if ($this->source !== null) {
            $source = $this->source->toArray();
            if ($source !== []) {
                $data['source'] = $source;
            }
        }

        if ($this->meta !== []) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }
}
