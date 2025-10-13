<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Relationship;

use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Query\Pagination;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use LogicException;
use Symfony\Component\HttpFoundation\Request;

final class LinkageBuilder
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly RelationshipReader $reader,
        private readonly PaginationConfig $paginationConfig,
    ) {
    }

    /**
     * @return array{0: 'to-one'|'to-many', 1: null|array<string, string>|list<array<string, string>>}
     */
    public function read(string $type, string $id, string $rel, Request $request): array
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $metadata = $this->registry->getByType($type);
        $relationship = $metadata->relationships[$rel] ?? null;

        if (!$relationship instanceof RelationshipMetadata) {
            throw new NotFoundException(sprintf('Relationship "%s" not found on resource "%s".', $rel, $type));
        }

        if ($relationship->toMany) {
            $pagination = $this->parsePagination($request);
            $slice = $this->reader->getToManyIds($type, $id, $rel, $pagination);
            $targetType = $this->determineTargetType($relationship, $rel);

            $data = array_map(
                static fn (string $targetId): array => ['type' => $targetType, 'id' => $targetId],
                $slice->ids,
            );

            return ['to-many', $data];
        }

        $targetId = $this->reader->getToOneId($type, $id, $rel);

        if ($targetId === null) {
            return ['to-one', null];
        }

        return ['to-one', ['type' => $this->determineTargetType($relationship, $rel), 'id' => $targetId]];
    }

    /**
     * @param array{type: string, id: string}|null $data
     */
    public function toIdentifierOrNull(?array $data): ?ResourceIdentifier
    {
        if ($data === null) {
            return null;
        }

        return new ResourceIdentifier($data['type'], $data['id']);
    }

    /**
     * @param list<array{type: string, id: string}> $data
     *
     * @return list<ResourceIdentifier>
     */
    public function toIdentifiers(array $data): array
    {
        return array_map(
            static fn (array $identifier): ResourceIdentifier => new ResourceIdentifier($identifier['type'], $identifier['id']),
            $data,
        );
    }

    private function determineTargetType(RelationshipMetadata $relationship, string $default): string
    {
        if ($relationship->targetType !== null) {
            return $relationship->targetType;
        }

        if ($relationship->targetClass !== null) {
            $metadata = $this->registry->getByClass($relationship->targetClass);

            if ($metadata !== null) {
                return $metadata->type;
            }
        }

        if ($this->registry->hasType($default)) {
            return $default;
        }

        throw new LogicException(sprintf('Unable to determine target type for relationship "%s".', $relationship->name));
    }

    private function parsePagination(Request $request): Pagination
    {
        /** @var array<string, mixed> $page */
        $page = (array) $request->query->all('page');

        $number = $page['number'] ?? 1;
        $size = $page['size'] ?? $this->paginationConfig->defaultSize;

        $number = $this->toInt($number, 'page[number]');
        $size = $this->toInt($size, 'page[size]');

        if ($number < 1) {
            throw new BadRequestException('page[number] must be greater than or equal to 1.');
        }

        if ($size < 1 || $size > $this->paginationConfig->maxSize) {
            throw new BadRequestException(sprintf(
                'page[size] must be between 1 and %d.',
                $this->paginationConfig->maxSize,
            ));
        }

        return new Pagination($number, $size);
    }

    private function toInt(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || !preg_match('/^-?\d+$/', $value)) {
            throw new BadRequestException(sprintf('%s must be an integer.', $name));
        }

        return (int) $value;
    }
}
