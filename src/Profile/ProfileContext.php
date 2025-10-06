<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile;

use JsonApi\Symfony\Profile\Hook\DocumentHook;
use JsonApi\Symfony\Profile\Hook\QueryHook;
use JsonApi\Symfony\Profile\Hook\ReadHook;
use JsonApi\Symfony\Profile\Hook\RelationshipHook;
use JsonApi\Symfony\Profile\Hook\WriteHook;
use Symfony\Component\HttpFoundation\Request;

final class ProfileContext
{
    public const REQUEST_ATTRIBUTE = '_jsonapi_profile_context';

    /** @var array<string, ProfileInterface> */
    private array $activeProfiles;

    /** @var array<string, list<ProfileInterface>> */
    private array $profilesPerType;

    /** @var array<string, list<string>> */
    private array $sources;

    /** @var list<DocumentHook>|null */
    private ?array $documentHooks = null;

    /** @var list<QueryHook>|null */
    private ?array $queryHooks = null;

    /** @var list<ReadHook>|null */
    private ?array $readHooks = null;

    /** @var list<WriteHook>|null */
    private ?array $writeHooks = null;

    /** @var list<RelationshipHook>|null */
    private ?array $relationshipHooks = null;

    /**
     * @param array<string, ProfileInterface>      $activeProfiles
     * @param array<string, list<ProfileInterface>> $profilesPerType
     * @param array<string, list<string>>          $sources
     */
    public function __construct(array $activeProfiles, array $profilesPerType = [], array $sources = [])
    {
        $this->activeProfiles = $activeProfiles;
        $this->profilesPerType = $profilesPerType;
        $this->sources = $sources;
    }

    public static function fromRequest(Request $request): ?self
    {
        $context = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        return $context instanceof self ? $context : null;
    }

    public static function store(Request $request, self $context): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $context);
    }

    /**
     * @return list<string>
     */
    public function activeUris(): array
    {
        return array_keys($this->activeProfiles);
    }

    public function has(string $uri): bool
    {
        return isset($this->activeProfiles[$uri]);
    }

    public function profile(string $uri): ?ProfileInterface
    {
        return $this->activeProfiles[$uri] ?? null;
    }

    /**
     * @return list<ProfileInterface>
     */
    public function profiles(): array
    {
        return array_values($this->activeProfiles);
    }

    /**
     * @return list<ProfileInterface>
     */
    public function profilesForType(string $type): array
    {
        $forType = $this->profilesPerType[$type] ?? [];
        if ($forType === []) {
            return $this->profiles();
        }

        $union = $this->activeProfiles;
        foreach ($forType as $profile) {
            $union[$profile->uri()] = $profile;
        }

        return array_values($union);
    }

    /**
     * @return array<string, list<string>>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * @return list<DocumentHook>
     */
    public function documentHooks(): array
    {
        if ($this->documentHooks === null) {
            $this->documentHooks = $this->collectHooks(DocumentHook::class);
        }

        return $this->documentHooks;
    }

    /**
     * @return list<QueryHook>
     */
    public function queryHooks(): array
    {
        if ($this->queryHooks === null) {
            $this->queryHooks = $this->collectHooks(QueryHook::class);
        }

        return $this->queryHooks;
    }

    /**
     * @return list<ReadHook>
     */
    public function readHooks(): array
    {
        if ($this->readHooks === null) {
            $this->readHooks = $this->collectHooks(ReadHook::class);
        }

        return $this->readHooks;
    }

    /**
     * @return list<WriteHook>
     */
    public function writeHooks(): array
    {
        if ($this->writeHooks === null) {
            $this->writeHooks = $this->collectHooks(WriteHook::class);
        }

        return $this->writeHooks;
    }

    /**
     * @return list<RelationshipHook>
     */
    public function relationshipHooks(): array
    {
        if ($this->relationshipHooks === null) {
            $this->relationshipHooks = $this->collectHooks(RelationshipHook::class);
        }

        return $this->relationshipHooks;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $hookInterface
     *
     * @return list<T>
     */
    private function collectHooks(string $hookInterface): array
    {
        $instances = [];
        foreach ($this->activeProfiles as $profile) {
            foreach ($profile->hooks() as $hook) {
                if ($hook instanceof $hookInterface) {
                    $instances[] = $hook;
                }
            }
        }

        return $instances;
    }
}
