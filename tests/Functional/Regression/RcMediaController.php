<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression;

use AlexFigures\JsonApi\Bridge\Symfony\Routing\Attribute\MediaChannel;
use Symfony\Component\HttpFoundation\Response;

final class RcMediaController
{
    public function version(string $version): Response
    {
        $response = new Response('{"data":null}', headers: ['Content-Type' => 'application/vnd.api+json']);
        if ($version !== 'absent') {
            $response->headers->set('X-Resource-Version', $version);
        }
        return $response;
    }

    #[\AlexFigures\JsonApi\Docs\Attribute\OpenApiEndpoint(
        summary: 'Example endpoint',
        requestBody: new \AlexFigures\JsonApi\Docs\Attribute\OpenApiRequestBody('application/json', ['type' => 'object']),
        responses: [200 => new \AlexFigures\JsonApi\Docs\Attribute\OpenApiResponse('Example response', 'application/json', ['type' => 'object'])],
        examples: ['sample' => new \AlexFigures\JsonApi\Docs\Attribute\OpenApiExample('Example input', ['title' => 'Example'], 'Public example')],
    )]
    public function plain(): Response
    {
        return new Response('plain', headers: ['Content-Type' => 'text/plain']);
    }

    #[MediaChannel('html')]
    public function html(): Response
    {
        return new Response('<html>OK</html>', headers: ['Content-Type' => 'text/html']);
    }
}
