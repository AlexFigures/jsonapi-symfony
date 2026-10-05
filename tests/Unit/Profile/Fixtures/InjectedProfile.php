<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Profile\Fixtures;

use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Profile\Validation\FieldRequirement;
use AlexFigures\Symfony\Profile\Validation\ProfileRequirements;

final readonly class InjectedProfile implements ProfileInterface
{
    public function __construct(private InjectedProfileContext $context)
    {
    }

    public function uri(): string
    {
        return $this->context->uri;
    }

    public function descriptor(): ProfileDescriptor
    {
        return new ProfileDescriptor($this->uri(), 'Injected profile', '1.0');
    }

    public function hooks(): iterable
    {
        return [];
    }

    public function requirements(): ?ProfileRequirements
    {
        return $this->context->requireMissingField ? new ProfileRequirements(fields: ['missing' => new FieldRequirement('string')]) : null;
    }
}
