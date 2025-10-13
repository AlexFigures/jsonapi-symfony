<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile;

use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;

interface ProfileInterface
{
    /**
     * Returns the unique URI of the profile as defined by RFC 6906.
     */
    public function uri(): string;

    /**
     * Provides metadata for DX tooling and documentation.
     */
    public function descriptor(): ProfileDescriptor;

    /**
     * @return iterable<object> List of hook implementations exposed by the profile.
     */
    public function hooks(): iterable;
}
