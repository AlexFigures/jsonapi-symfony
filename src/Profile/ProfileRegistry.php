<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile;

use AlexFigures\JsonApi\Profile\Descriptor\ProfileDescriptor;

/** @api */
final class ProfileRegistry
{
    /** @var array<string, ProfileInterface> */
    private array $profiles = [];

    /**
     * @param iterable<ProfileInterface>  $profiles
     * @param array<string, class-string> $resources
     * @param list<string>                $defaultProfiles
     * @param array<string, list<string>> $perType
     */
    public function __construct(iterable $profiles = [], array $resources = [], array $defaultProfiles = [], array $perType = [])
    {
        foreach ($profiles as $profile) {
            $this->register($profile);
        }
        $enabled = [];
        foreach ($resources as $type => $class) {
            $enabled[$type] = array_values(array_unique(array_merge($defaultProfiles, $perType[$type] ?? [])));
        }
        $validation = (new \AlexFigures\JsonApi\Profile\Validation\ReflectionProfileValidator())->validate($this->profiles, $resources, $enabled);
        if ($validation->hasErrors()) {
            throw new \LogicException('Profile validation failed: ' . implode("\n", $validation->formatErrors()));
        }
    }

    public function register(ProfileInterface $profile): void
    {
        $this->profiles[$profile->uri()] = $profile;
    }

    /**
     * @return array<string, ProfileInterface>
     */
    public function all(): array
    {
        return $this->profiles;
    }

    public function has(string $uri): bool
    {
        return isset($this->profiles[$uri]);
    }

    public function get(string $uri): ?ProfileInterface
    {
        return $this->profiles[$uri] ?? null;
    }

    /**
     * @return array<string, ProfileDescriptor>
     */
    public function descriptors(): array
    {
        $descriptors = [];
        foreach ($this->profiles as $profile) {
            $descriptors[$profile->uri()] = $profile->descriptor();
        }

        return $descriptors;
    }
}
