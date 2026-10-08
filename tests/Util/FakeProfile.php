<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Util;

use AlexFigures\JsonApi\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\JsonApi\Profile\ProfileInterface;
use AlexFigures\JsonApi\Profile\Validation\ProfileRequirements;

final readonly class FakeProfile implements ProfileInterface
{
    /**
     * @param iterable<object> $hooks
     */
    public function __construct(
        private string $uri,
        private iterable $hooks = [],
        private ?ProfileDescriptor $descriptor = null,
        private string $name = 'Fake Profile',
        private string $version = '1.0.0',
        private ?ProfileRequirements $requirements = null,
    ) {
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function descriptor(): ProfileDescriptor
    {
        return $this->descriptor ?? new ProfileDescriptor($this->uri, $this->name, $this->version);
    }

    public function hooks(): iterable
    {
        return $this->hooks;
    }

    public function requirements(): ?ProfileRequirements
    {
        return $this->requirements;
    }
}
