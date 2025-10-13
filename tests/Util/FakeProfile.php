<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Util;

use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\Symfony\Profile\ProfileInterface;

final class FakeProfile implements ProfileInterface
{
    /**
     * @param iterable<object> $hooks
     */
    public function __construct(
        private readonly string $uri,
        private readonly iterable $hooks = [],
        private readonly ?ProfileDescriptor $descriptor = null,
        private readonly string $name = 'Fake Profile',
        private readonly string $version = '1.0.0',
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
}
