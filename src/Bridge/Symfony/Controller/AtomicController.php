<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Controller;

use JsonApi\Symfony\Atomic\Execution\OperationDispatcher;
use JsonApi\Symfony\Atomic\Parser\AtomicRequestParser;
use JsonApi\Symfony\Atomic\Validation\AtomicValidator;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Negotiation\MediaTypeNegotiator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/operations', methods: ['POST'], name: 'jsonapi.atomic')]
final class AtomicController
{
    public function __construct(
        private readonly AtomicRequestParser $parser,
        private readonly AtomicValidator $validator,
        private readonly OperationDispatcher $dispatcher,
        private readonly MediaTypeNegotiator $negotiator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->negotiator->assertAtomicExt($request);
        $operations = $this->parser->parse($request);
        [$validated, $lids] = $this->validator->validate($operations);
        [$resultSet, $allEmpty] = $this->dispatcher->run($validated, $lids);

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
