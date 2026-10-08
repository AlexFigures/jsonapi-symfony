<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\Controller;

use AlexFigures\JsonApi\Atomic\Execution\OperationDispatcher;
use AlexFigures\JsonApi\Atomic\Parser\AtomicRequestParser;
use AlexFigures\JsonApi\Atomic\Validation\AtomicValidator;
use AlexFigures\JsonApi\Http\Negotiation\MediaType;
use AlexFigures\JsonApi\Http\Negotiation\MediaTypeNegotiator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/operations', methods: ['POST'], name: 'jsonapi.atomic')]
/** @api Supported callable for explicit Atomic route registration. */
final readonly class AtomicController
{
    /** @internal Container wiring. */
    public function __construct(
        private AtomicRequestParser $parser,
        private AtomicValidator $validator,
        private OperationDispatcher $dispatcher,
        private MediaTypeNegotiator $negotiator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->negotiator->assertAtomicExt($request);
        $operations = $this->parser->parse($request);
        [$validated, $lids] = $this->validator->validate($operations, $request);
        try {
            [$resultSet, $allEmpty] = $this->dispatcher->run($validated, $lids);
        } catch (\AlexFigures\JsonApi\Http\Exception\JsonApiHttpException $exception) {
            // Commit errors apply to the batch when no individual operation can be identified.
            throw \AlexFigures\JsonApi\Http\Error\AtomicErrorRebaser::rebase($exception, '/atomic:operations', true);
        }

        if ($allEmpty) {
            return new Response(null, Response::HTTP_NO_CONTENT, ['Content-Type' => MediaType::JSON_API_ATOMIC]);
        }

        return new JsonResponse([
            'atomic:results' => $resultSet,
        ], JsonResponse::HTTP_OK, [
            'Content-Type' => MediaType::JSON_API_ATOMIC,
            'Vary' => 'Accept',
        ]);
    }
}
