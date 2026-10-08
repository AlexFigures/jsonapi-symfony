<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Docs\OpenApi\OpenApiSpecGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/_jsonapi/openapi.json', name: 'jsonapi.docs.openapi', methods: ['GET'])]
/** @internal */
final readonly class OpenApiController
{
    /**
     * @param array{enabled?: bool} $config
     */
    public function __construct(
        private OpenApiSpecGenerator $generator,
        private array $config,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        if (($this->config['enabled'] ?? false) !== true) {
            throw new NotFoundHttpException('OpenAPI generation is disabled.');
        }

        $spec = $this->generator->generate();

        return new JsonResponse(
            $spec,
            JsonResponse::HTTP_OK,
            ['Content-Type' => 'application/vnd.oai.openapi+json'],
        );
    }
}
