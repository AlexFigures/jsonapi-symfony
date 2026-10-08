<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Result;

use AlexFigures\JsonApi\Atomic\AtomicConfig;
use AlexFigures\JsonApi\Atomic\Execution\OperationOutcome;
use AlexFigures\JsonApi\Atomic\Operation;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Query\Criteria;
use Symfony\Component\HttpFoundation\Request;

/** @internal */
final readonly class ResultBuilder
{
    public function __construct(
        private AtomicConfig $config,
        private DocumentBuilder $documents,
    ) {
    }

    /**
     * @param list<Operation>        $operations
     * @param list<OperationOutcome> $outcomes
     *
     * @return array{0: list<array<string, mixed>|\stdClass>, 1: bool}
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
                $results[] = new \stdClass();
                continue;
            }

            if (!$outcome->hasData) {
                $results[] = new \stdClass();
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
