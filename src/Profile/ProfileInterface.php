<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile;

use AlexFigures\JsonApi\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\JsonApi\Profile\Validation\ProfileRequirements;

/** @api */
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

    /**
     * Returns the requirements this profile imposes on entities.
     *
     * Profiles can declare requirements for attributes and fields that must be present
     * on entities before the profile can be used. These requirements are validated
     * at compile-time to prevent runtime errors.
     *
     * Return null if the profile has no requirements (e.g., read-only profiles that
     * only modify document structure).
     *
     * @return ProfileRequirements|null Requirements for this profile, or null if no requirements
     */
    public function requirements(): ?ProfileRequirements;
}
