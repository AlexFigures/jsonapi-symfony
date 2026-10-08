<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Error;

/** @api */
final readonly class ErrorObject
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public ?string $id,
        public ?string $aboutLink,
        public string $status,
        public string $code,
        public ?string $title,
        public ?string $detail,
        public ?ErrorSource $source,
        public array $meta = [],
        public ?string $typeLink = null,
    ) {
    }

    public function withId(?string $id): self
    {
        if ($id === $this->id) {
            return $this;
        }

        return new self($id, $this->aboutLink, $this->status, $this->code, $this->title, $this->detail, $this->source, $this->meta, $this->typeLink);
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
            $this->typeLink,
        );
    }

    public function withAboutLink(?string $link): self
    {
        if ($link === $this->aboutLink) {
            return $this;
        }

        return new self($this->id, $link, $this->status, $this->code, $this->title, $this->detail, $this->source, $this->meta, $this->typeLink);
    }

    public function withTypeLink(?string $link): self
    {
        if ($link === $this->typeLink) {
            return $this;
        }
        return new self($this->id, $this->aboutLink, $this->status, $this->code, $this->title, $this->detail, $this->source, $this->meta, $link);
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

        if ($this->typeLink !== null) {
            $data['links']['type'] = $this->typeLink;
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
