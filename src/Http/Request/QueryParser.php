<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Request;

use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;
use JsonApi\Symfony\Query\Sorting;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class QueryParser
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly PaginationConfig $paginationConfig,
        private readonly SortingWhitelist $sortingWhitelist,
    ) {
    }

    public function parse(string $type, Request $request): Criteria
    {
        $pagination = $this->parsePagination($request);
        $criteria = new Criteria($pagination);

        $criteria->fields = $this->parseFields($request);
        $criteria->include = $this->parseInclude($type, $request);
        $criteria->sort = $this->parseSort($type, $request);

        return $criteria;
    }

    private function parsePagination(Request $request): Pagination
    {
        $page = $request->query->all('page');
        if (!is_array($page)) {
            $page = $request->query->all()['page'] ?? [];
            if (!is_array($page)) {
                $page = [];
            }
        }

        $number = $page['number'] ?? 1;
        $size = $page['size'] ?? $this->paginationConfig->defaultSize;

        $number = $this->toInt($number, 'page[number]');
        $size = $this->toInt($size, 'page[size]');

        if ($number < 1) {
            throw new BadRequestHttpException('page[number] must be greater than or equal to 1.');
        }

        if ($size < 1 || $size > $this->paginationConfig->maxSize) {
            throw new BadRequestHttpException(sprintf(
                'page[size] must be between 1 and %d.',
                $this->paginationConfig->maxSize
            ));
        }

        return new Pagination($number, $size);
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseFields(Request $request): array
    {
        $query = $request->query->all();
        $fields = [];

        if (!isset($query['fields'])) {
            return $fields;
        }

        if (!is_array($query['fields'])) {
            throw new BadRequestHttpException('fields parameter must be an array keyed by resource type.');
        }

        /** @var array<string, mixed> $rawFields */
        $rawFields = $query['fields'];

        foreach ($rawFields as $resourceType => $list) {
            if (!is_string($resourceType) || $resourceType === '') {
                throw new BadRequestHttpException('fields parameter keys must be resource types.');
            }

            if (!is_string($list)) {
                throw new BadRequestHttpException(sprintf('fields[%s] must be a comma separated string.', $resourceType));
            }

            $entries = array_values(array_filter(array_map('trim', explode(',', $list)), static fn (string $value): bool => $value !== ''));
            if ($entries === []) {
                $fields[$resourceType] = [];
                continue;
            }

            $metadata = $this->requireResourceMetadata($resourceType);
            $allowed = array_merge(array_keys($metadata->attributes), array_keys($metadata->relationships));
            if ($metadata->exposeId) {
                $allowed[] = 'id';
            }

            $unique = [];
            foreach ($entries as $entry) {
                if (!in_array($entry, $allowed, true)) {
                    throw new BadRequestHttpException(sprintf('Unknown field "%s" for resource type "%s".', $entry, $resourceType));
                }

                if (!in_array($entry, $unique, true)) {
                    $unique[] = $entry;
                }
            }

            $fields[$resourceType] = $unique;
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function parseInclude(string $type, Request $request): array
    {
        $raw = $request->query->get('include');
        if ($raw === null || $raw === '') {
            return [];
        }

        if (!is_string($raw)) {
            throw new BadRequestHttpException('include parameter must be a string.');
        }

        $paths = [];
        foreach (array_map('trim', explode(',', $raw)) as $path) {
            if ($path === '') {
                continue;
            }

            $this->validateIncludePath($type, $path);

            if (!in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @return list<Sorting>
     */
    private function parseSort(string $type, Request $request): array
    {
        $raw = $request->query->get('sort');
        if ($raw === null || $raw === '') {
            return [];
        }

        if (!is_string($raw)) {
            throw new BadRequestHttpException('sort parameter must be a string.');
        }

        $allowed = $this->sortingWhitelist->allowedFor($type);
        $result = [];

        foreach (array_map('trim', explode(',', $raw)) as $sortField) {
            if ($sortField === '') {
                continue;
            }

            $desc = str_starts_with($sortField, '-');
            $field = ltrim($sortField, '-');

            if (!in_array($field, $allowed, true)) {
                throw new BadRequestHttpException(sprintf('Sorting by "%s" is not allowed for "%s".', $field, $type));
            }

            $result[] = new Sorting($field, $desc);
        }

        return $result;
    }

    private function validateIncludePath(string $rootType, string $path): void
    {
        $segments = explode('.', $path);
        $currentType = $rootType;

        foreach ($segments as $index => $segment) {
            $metadata = $this->requireResourceMetadata($currentType);

            if (!isset($metadata->relationships[$segment])) {
                throw new BadRequestHttpException(sprintf('Unknown relationship "%s" on resource "%s".', $segment, $currentType));
            }

            $relationship = $metadata->relationships[$segment];
            $targetType = $relationship->targetType;

            if ($targetType === null && $relationship->targetClass !== null) {
                $targetMetadata = $this->registry->getByClass($relationship->targetClass);
                $targetType = $targetMetadata?->type;
            }

            if ($index < count($segments) - 1) {
                if ($targetType === null) {
                    throw new BadRequestHttpException(sprintf('Relationship "%s" on resource "%s" cannot be chained without a target type.', $segment, $currentType));
                }

                $currentType = $targetType;
            }
        }
    }

    private function requireResourceMetadata(string $type): ResourceMetadata
    {
        if (!$this->registry->hasType($type)) {
            throw new BadRequestHttpException(sprintf('Unknown resource type "%s".', $type));
        }

        return $this->registry->getByType($type);
    }

    private function toInt(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('%s must be an integer.', $name));
        }

        if (!preg_match('/^-?\d+$/', $value)) {
            throw new BadRequestHttpException(sprintf('%s must be an integer.', $name));
        }

        return (int) $value;
    }
}
