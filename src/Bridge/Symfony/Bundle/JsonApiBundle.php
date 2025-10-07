<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Bundle;

use JsonApi\Symfony\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @psalm-suppress MissingConstructor
 */
final class JsonApiBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new JsonApiExtension();
        }

        return $this->extension;
    }
}
