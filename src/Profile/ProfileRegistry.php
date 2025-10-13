<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile;

use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;

final class ProfileRegistry
{
    /** @var array<string, ProfileInterface> */
    private array $profiles = [];

    /**
     * @param iterable<ProfileInterface> $profiles
     */
    public function __construct(iterable $profiles = [])
    {
        foreach ($profiles as $profile) {
            $this->register($profile);
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
