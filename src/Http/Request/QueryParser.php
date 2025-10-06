<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Request;

use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Error\ErrorObject;
use JsonApi\Symfony\Http\Error\ErrorSource;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;
use JsonApi\Symfony\Query\Sorting;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Http\Safety\LimitsEnforcer;
use Symfony\Component\HttpFoundation\Request;

final class QueryParser
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly PaginationConfig $paginationConfig,
        private readonly SortingWhitelist $sortingWhitelist,
        private readonly ErrorMapper $errors,
        private readonly ?LimitsEnforcer $limits = null,
    ) {
    }

    public function parse(string $type, Request $request): Criteria
    {
        $pagination = $this->parsePagination($request);
        $criteria = new Criteria($pagination);

        $criteria->fields = $this->parseFields($request);
        $criteria->include = $this->parseInclude($type, $request);
        $criteria->sort = $this->parseSort($type, $request);

        $this->limits?->enforce($type, $criteria);

        return $criteria;
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
            $this->throwBadRequest($this->errors->invalidParameter('page[number]', 'page[number] must be greater than or equal to 1.'));
        }

        if ($size < 1) {
            $this->throwBadRequest($this->errors->invalidParameter('page[size]', 'page[size] must be greater than or equal to 1.'));
        }

        if ($size > $this->paginationConfig->maxSize) {
            $this->throwBadRequest($this->errors->pageSizeTooLarge($this->paginationConfig->maxSize));
        }

        return new Pagination($number, $size);
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseFields(Request $request): array
    {
        /** @var array<string, mixed> $query */
        $query = $request->query->all();
        $fields = [];

        if (!isset($query['fields'])) {
            return $fields;
        }

        if (!is_array($query['fields'])) {
            $this->throwBadRequest($this->errors->invalidParameter('fields', 'fields parameter must be an object keyed by resource type.'));
        }

        /** @var array<int|string, mixed> $rawFields */
        $rawFields = $query['fields'];

        foreach ($rawFields as $resourceType => $list) {
            if (!is_string($resourceType) || $resourceType === '') {
                $this->throwBadRequest($this->errors->invalidParameter('fields', 'fields parameter keys must be resource types.'));
            }

            if (!is_string($list)) {
                $this->throwBadRequest($this->errors->invalidParameter(sprintf('fields[%s]', $resourceType), sprintf('fields[%s] must be a comma separated string.', $resourceType)));
            }

            $entries = array_values(array_filter(array_map('trim', explode(',', $list)), static fn (string $value): bool => $value !== ''));
            if ($entries === []) {
                $fields[$resourceType] = [];
                continue;
            }

            // Validate resource type exists (for query parameters, this should be 400, not 404)
            if (!$this->registry->hasType($resourceType)) {
                $error = $this->errors->unknownType($resourceType);
                // Override status to 400 for query parameter context
                $error = new ErrorObject(
                    id: $error->id,
                    aboutLink: $error->aboutLink,
                    status: '400',
                    code: $error->code,
                    title: $error->title,
                    detail: $error->detail,
                    source: new ErrorSource(parameter: sprintf('fields[%s]', $resourceType)),
                    meta: $error->meta,
                );
                $this->throwBadRequest($error);
            }

            $metadata = $this->registry->getByType($resourceType);
            $allowed = array_merge(array_keys($metadata->attributes), array_keys($metadata->relationships));
            if ($metadata->exposeId) {
                $allowed[] = 'id';
            }

            $unique = [];
            foreach ($entries as $entry) {
                if (!in_array($entry, $allowed, true)) {
                    $this->throwBadRequest($this->errors->unknownField($resourceType, $entry));
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
            $this->throwBadRequest($this->errors->invalidParameter('include', 'include parameter must be a string.'));
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
            $this->throwBadRequest($this->errors->invalidParameter('sort', 'sort parameter must be a string.'));
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
                $this->throwBadRequest($this->errors->sortFieldNotAllowed($type, $field));
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
                $this->throwBadRequest($this->errors->invalidParameter('include', sprintf('Unknown relationship "%s" on resource "%s".', $segment, $currentType)));
            }

            $relationship = $metadata->relationships[$segment];
            $targetType = $relationship->targetType;

            if ($targetType === null && $relationship->targetClass !== null) {
                $targetMetadata = $this->registry->getByClass($relationship->targetClass);
                $targetType = $targetMetadata?->type;
            }

            if ($index < count($segments) - 1) {
                if ($targetType === null) {
                    $this->throwBadRequest($this->errors->invalidParameter('include', sprintf('Relationship "%s" on resource "%s" cannot be chained without a target type.', $segment, $currentType)));
                }

                $currentType = $targetType;
            }
        }
    }

    private function requireResourceMetadata(string $type): ResourceMetadata
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException('Unknown resource type.', [$this->errors->unknownType($type)]);
        }

        return $this->registry->getByType($type);
    }

    private function toInt(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value)) {
            $this->throwBadRequest($this->errors->invalidParameter($name, sprintf('%s must be an integer.', $name)));
        }

        if (!preg_match('/^-?\d+$/', $value)) {
            $this->throwBadRequest($this->errors->invalidParameter($name, sprintf('%s must be an integer.', $name)));
        }

        return (int) $value;
    }

    /**
     * @throws BadRequestException
     */
    private function throwBadRequest(ErrorObject $error): never
    {
        throw new BadRequestException('Invalid query parameter.', [$error]);
    }
}
