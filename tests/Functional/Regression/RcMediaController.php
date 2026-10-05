<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

use AlexFigures\Symfony\Bridge\Symfony\Routing\Attribute\MediaChannel;
use Symfony\Component\HttpFoundation\Response;

final class RcMediaController
{
    #[\AlexFigures\Symfony\Docs\Attribute\OpenApiEndpoint(
        summary: 'Example endpoint',
        requestBody: new \AlexFigures\Symfony\Docs\Attribute\OpenApiRequestBody('application/json', ['type' => 'object']),
        responses: [200 => new \AlexFigures\Symfony\Docs\Attribute\OpenApiResponse('Example response', 'application/json', ['type' => 'object'])],
        examples: ['sample' => new \AlexFigures\Symfony\Docs\Attribute\OpenApiExample('Example input', ['title' => 'Example'], 'Public example')],
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
