<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/_jsonapi/openapi.json', name: 'jsonapi.docs.openapi', methods: ['GET'])]
final class OpenApiController
{
    /**
     * @param array{enabled: bool} $config
     */
    public function __construct(
        private readonly OpenApiSpecGenerator $generator,
        private readonly array $config,
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
