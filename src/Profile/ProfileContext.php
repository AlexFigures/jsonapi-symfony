<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile;

use AlexFigures\JsonApi\Profile\Hook\DocumentHook;
use AlexFigures\JsonApi\Profile\Hook\QueryHook;
use AlexFigures\JsonApi\Profile\Hook\ReadHook;
use AlexFigures\JsonApi\Profile\Hook\RelationshipHook;
use AlexFigures\JsonApi\Profile\Hook\WriteHook;
use Symfony\Component\HttpFoundation\Request;

/** @api */
final class ProfileContext
{
    public const REQUEST_ATTRIBUTE = '_jsonapi_profile_context';

    private readonly AttributeReader $attributeReader;

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
     * @param array<string, ProfileInterface>       $activeProfiles
     * @param array<string, list<ProfileInterface>> $profilesPerType
     * @param array<string, list<string>>           $sources
     */
    public function __construct(
        private array $activeProfiles,
        private array $profilesPerType = [],
        private readonly array $sources = [],
        ?AttributeReader $attributeReader = null,
        public readonly ?\AlexFigures\JsonApi\Query\Fetch\RelationshipReadMap $relationshipReads = null,
        public readonly bool $relatedEndpoint = false,
    ) {
        $this->attributeReader = $attributeReader ?? new AttributeReader();
    }

    public static function fromRequest(Request $request): ?self
    {
        $context = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if (!$context instanceof self) {
            return null;
        }
        return $request->attributes->get('_jsonapi_related_endpoint') === true ? new self($context->activeProfiles, $context->profilesPerType, $context->sources, $context->attributeReader, $context->relationshipReads, true) : $context;
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

    public function forType(string $type): self
    {
        $profiles = [];
        foreach ($this->profilesForType($type) as $profile) {
            $profiles[$profile->uri()] = $profile;
        }
        return new self($profiles, [], $this->sources, $this->attributeReader, $this->relationshipReads, $this->relatedEndpoint);
    }

    public function withRelationshipReads(?\AlexFigures\JsonApi\Query\Fetch\RelationshipReadMap $reads): self
    {
        return new self($this->activeProfiles, $this->profilesPerType, $this->sources, $this->attributeReader, $reads, $this->relatedEndpoint);
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
     * Get the attribute reader for reading PHP 8 attributes from entity classes.
     */
    public function attributeReader(): AttributeReader
    {
        return $this->attributeReader;
    }

    /**
     * @return list<DocumentHook>
     */
    public function documentHooks(): array
    {
        $this->documentHooks ??= $this->collectHooks(DocumentHook::class);

        return $this->documentHooks;
    }

    /**
     * @return list<QueryHook>
     */
    public function queryHooks(): array
    {
        $this->queryHooks ??= $this->collectHooks(QueryHook::class);

        return $this->queryHooks;
    }

    /**
     * @return list<ReadHook>
     */
    public function readHooks(): array
    {
        $this->readHooks ??= $this->collectHooks(ReadHook::class);

        return $this->readHooks;
    }

    /**
     * @return list<WriteHook>
     */
    public function writeHooks(): array
    {
        $this->writeHooks ??= $this->collectHooks(WriteHook::class);

        return $this->writeHooks;
    }

    /**
     * @return list<RelationshipHook>
     */
    public function relationshipHooks(): array
    {
        $this->relationshipHooks ??= $this->collectHooks(RelationshipHook::class);

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
