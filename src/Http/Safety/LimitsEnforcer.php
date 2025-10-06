<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Safety;

use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Query\Criteria;

final class LimitsEnforcer
{
    /**
     * @param array<string, int> $config
     */
    public function __construct(
        private readonly ErrorMapper $errors,
        private readonly RequestComplexityScorer $scorer,
        private readonly array $config,
    ) {
    }

    public function enforce(string $type, Criteria $criteria): void
    {
        $this->enforceIncludeLimits($criteria);
        $this->enforceFieldsLimits($criteria);
        $this->enforcePagination($criteria);
        $this->enforceComplexity($criteria);
    }

    public function assertIncludedCount(int $count): void
    {
        $limit = $this->config['included_max_resources'] ?? 0;
        if ($limit > 0 && $count > $limit) {
            $error = $this->errors->includedResourcesLimit($limit);

            throw new BadRequestException('Too many included resources.', [$error]);
        }
    }

    private function enforceIncludeLimits(Criteria $criteria): void
    {
        $maxPaths = $this->config['include_max_paths'] ?? 0;
        if ($maxPaths > 0 && count($criteria->include) > $maxPaths) {
            $error = $this->errors->invalidParameter('include', sprintf('Only %d include paths are allowed per request.', $maxPaths));

            throw new BadRequestException('Too many include paths.', [$error]);
        }

        $maxDepth = $this->config['include_max_depth'] ?? 0;
        if ($maxDepth <= 0) {
            return;
        }

        foreach ($criteria->include as $path) {
            $depth = substr_count($path, '.') + 1;
            if ($depth > $maxDepth) {
                $error = $this->errors->invalidParameter('include', sprintf('Include depth cannot exceed %d.', $maxDepth));

                throw new BadRequestException('Include depth exceeded.', [$error]);
            }
        }
    }

    private function enforceFieldsLimits(Criteria $criteria): void
    {
        $fieldsLimit = $this->config['fields_max_total'] ?? 0;
        if ($fieldsLimit <= 0) {
            return;
        }

        $total = 0;
        foreach ($criteria->fields as $fields) {
            $total += count($fields);
        }

        if ($total > $fieldsLimit) {
            $error = $this->errors->invalidParameter('fields', sprintf('A maximum of %d fields can be requested.', $fieldsLimit));

            throw new BadRequestException('Too many fields requested.', [$error]);
        }
    }

    private function enforcePagination(Criteria $criteria): void
    {
        $pageMax = $this->config['page_max_size'] ?? 0;
        if ($pageMax > 0 && $criteria->pagination->pageSize > $pageMax) {
            $error = $this->errors->invalidParameter('page[size]', sprintf('Page size cannot be greater than %d.', $pageMax));

            throw new BadRequestException('Page size too large.', [$error]);
        }
    }

    private function enforceComplexity(Criteria $criteria): void
    {
        $budget = $this->config['complexity_budget'] ?? 0;
        if ($budget <= 0) {
            return;
        }

        $score = $this->scorer->score($criteria);
        if ($score > $budget) {
            $error = $this->errors->requestTooComplex(sprintf('The request complexity score of %d exceeds the allowed budget of %d.', $score, $budget));

            throw new BadRequestException('Request too complex.', [$error]);
        }
    }
}
