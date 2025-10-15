<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Metadata;

use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Attribute\FilterableFields;
use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use AlexFigures\Symfony\Resource\Definition\ResourceDefinition;
use AlexFigures\Symfony\Resource\Definition\VersionDefinition;
use AlexFigures\Symfony\Resource\Definition\VersionResolverInterface;

/**
 * @psalm-type AttributeMap = array<string, AttributeMetadata>
 * @psalm-type RelationshipMap = array<string, RelationshipMetadata>
 */
final class ResourceMetadata
{
    public string $dataClass;

    public string $viewClass;

    public ReadProjection $readProjection;

    /**
     * @var array<string, string>
     */
    public array $fieldMap;

    /**
     * @var array<string, RelationshipLinkingPolicy>
     */
    public array $relationshipPolicies;

    /**
     * @var array<string, class-string>
     */
    public array $writeRequests;

    public ?VersionResolverInterface $versionResolver;

    /**
     * @param AttributeMap         $attributes
     * @param RelationshipMap      $relationships
     * @param class-string         $class
     * @param list<string>         $sortableFields
     * @param array<string, mixed> $normalizationContext
     * @param array<string, mixed> $denormalizationContext
     * @param class-string|null    $dataClass
     * @param class-string|null    $viewClass
     * @param array<string, string> $fieldMap
     * @param array<string, RelationshipLinkingPolicy> $relationshipPolicies
     * @param array<string, class-string> $writeRequests
     */
    public function __construct(
        public string $type,
        public string $class,
        public array $attributes,
        public array $relationships,
        public bool $exposeId = true,
        public ?string $idPropertyPath = null,
        public ?string $routePrefix = null,
        public ?string $description = null,
        public array $sortableFields = [],
        public ?FilterableFields $filterableFields = null,
        public ?OperationGroups $operationGroups = null,
        public array $normalizationContext = [],
        public array $denormalizationContext = [],
        ?string $dataClass = null,
        ?string $viewClass = null,
        ReadProjection $readProjection = ReadProjection::ENTITY,
        array $fieldMap = [],
        array $relationshipPolicies = [],
        array $writeRequests = [],
        ?VersionResolverInterface $versionResolver = null,
    ) {
        $this->dataClass = $dataClass ?? $class;
        $this->viewClass = $viewClass ?? $class;
        $this->readProjection = $readProjection;
        $this->fieldMap = $fieldMap;
        $this->relationshipPolicies = $relationshipPolicies;
        $this->writeRequests = $writeRequests;
        $this->versionResolver = $versionResolver;
    }

    public function getViewClass(): string
    {
        return $this->viewClass;
    }

    /**
     * Returns operation groups for this resource.
     *
     * @deprecated Use normalizationContext and denormalizationContext instead
     */
    public function getOperationGroups(): OperationGroups
    {
        return $this->operationGroups ?? OperationGroups::default();
    }

    /**
     * Get serialization groups for reading.
     *
     * @return list<string>
     */
    public function getNormalizationGroups(): array
    {
        return $this->normalizationContext['groups'] ?? [];
    }

    /**
     * Get serialization groups for writing.
     * Same groups are used for both create and update operations.
     *
     * @return list<string>
     */
    public function getDenormalizationGroups(): array
    {
        $groups = $this->denormalizationContext['groups'] ?? [];

        // Always add Default group for Symfony validation compatibility
        if (!in_array('Default', $groups, true)) {
            $groups[] = 'Default';
        }

        return $groups;
    }

    public function getDefinition(?ProfileContext $context = null): ResourceDefinition
    {
        $context ??= new ProfileContext([]);
        $versionDefinition = $this->resolveVersionDefinition($context);

        return new ResourceDefinition(
            type: $this->type,
            dataClass: $this->dataClass,
            viewClass: $versionDefinition->viewClass ?? $this->getViewClass(),
            readProjection: $versionDefinition->readProjection,
            fieldMap: $versionDefinition->fieldMap + $this->fieldMap,
            relationshipPolicies: $versionDefinition->relationshipPolicies + $this->relationshipPolicies,
            writeRequests: $versionDefinition->writeRequests + $this->writeRequests,
            versionResolver: $this->versionResolver,
        );
    }

    private function resolveVersionDefinition(ProfileContext $context): VersionDefinition
    {
        if ($this->versionResolver === null) {
            return new VersionDefinition(
                viewClass: $this->viewClass,
                writeRequests: $this->writeRequests,
                readProjection: $this->readProjection,
                fieldMap: $this->fieldMap,
                relationshipPolicies: $this->relationshipPolicies,
            );
        }

        return $this->versionResolver->resolve($context);
    }
}
