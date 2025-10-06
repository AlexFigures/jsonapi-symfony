<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Result;

use JsonApi\Symfony\Atomic\AtomicConfig;
use JsonApi\Symfony\Atomic\Execution\OperationOutcome;
use JsonApi\Symfony\Atomic\Operation;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Query\Criteria;
use Symfony\Component\HttpFoundation\Request;

final class ResultBuilder
{
    public function __construct(
        private readonly AtomicConfig $config,
        private readonly DocumentBuilder $documents,
    ) {
    }

    /**
     * @param list<Operation>        $operations
     * @param list<OperationOutcome> $outcomes
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    public function build(array $operations, array $outcomes): array
    {
        $results = [];
        $allEmpty = true;

        $request = Request::create($this->config->endpoint, 'POST');
        $criteria = new Criteria();

        foreach ($operations as $index => $operation) {
            $outcome = $outcomes[$index] ?? OperationOutcome::empty();

            if ($this->config->returnPolicy === 'none') {
                $results[] = [];
                continue;
            }

            if (!$outcome->hasData) {
                $results[] = [];
                continue;
            }

            $document = $this->documents->buildResource($outcome->type ?? '', $outcome->model ?? new \stdClass(), $criteria, $request);
            $results[] = ['data' => $document['data']];
            $allEmpty = false;
        }

        if ($this->config->returnPolicy === 'always') {
            $allEmpty = false;
        }

        return [$results, $allEmpty];
    }
}
