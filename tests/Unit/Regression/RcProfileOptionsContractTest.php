<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Regression;

use AlexFigures\JsonApi\Http\Document\Fetch\RepresentationFetchPlanner;
use AlexFigures\JsonApi\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\JsonApi\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Query\Fetch\RelationshipReadMap;
use AlexFigures\JsonApi\Resource\Metadata\RelationshipMetadata;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Tests\Unit\Regression\Fixtures\CountOwner;
use AlexFigures\JsonApi\Tests\Unit\Regression\Fixtures\SoftOwner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RcProfileOptionsContractTest extends TestCase
{
    public function testCountKeyAndRelatedPolicyAffectFetchPlanAndExposure(): void
    {
        $metadata = new ResourceMetadata('owners', CountOwner::class, [], ['tags' => new RelationshipMetadata('tags', true, 'tags')]);
        foreach ([true, false] as $enabled) {
            $profile = new RelationshipCountsProfile(['relationship_meta_key' => 'cardinality', 'compute_in_related_endpoints' => $enabled]);
            $context = new ProfileContext([$profile->uri() => $profile]);
            $request = Request::create('/api/owners/1/tags');
            ProfileContext::store($request, $context);
            $request->attributes->set('_jsonapi_related_endpoint', true);
            $relatedContext = ProfileContext::fromRequest($request)->forType('owners');
            $planner = new RepresentationFetchPlanner('never');
            self::assertCount(1, $planner->edges($metadata, new Criteria(), [], $context));
            self::assertCount($enabled ? 1 : 0, $planner->edges($metadata, new Criteria(), [], $relatedContext));
            $reads = new RelationshipReadMap();
            $reads->putCount('owners', '1', 'tags', 23);
            foreach ([$context, $relatedContext] as $endpointContext) {
                $relationships = ['tags' => ['links' => ['related' => '/tags']]];
                foreach ($endpointContext->documentHooks() as $hook) {
                    $hook->onResourceRelationships($endpointContext->withRelationshipReads($reads), $metadata, $relationships, new CountOwner());
                }
                if (!$endpointContext->relatedEndpoint || $enabled) {
                    self::assertSame(['cardinality' => 23], $relationships['tags']['meta']);
                } else {
                    self::assertArrayNotHasKey('meta', $relationships['tags']);
                }
            }
        }
    }

    public function testSoftDeleteReadsApplicationOwnedActorFieldWithoutPublishingItAsAttribute(): void
    {
        $profile = new SoftDeleteProfile();
        $context = new ProfileContext([$profile->uri() => $profile]);
        $metadata = new ResourceMetadata('soft', SoftOwner::class, [], []);
        $meta = [];
        foreach ($context->documentHooks() as $hook) {
            if ($hook instanceof \AlexFigures\JsonApi\Profile\Hook\ResourceMetaHookInterface) {
                $hook->onResourceMeta($context, $metadata, $meta, new SoftOwner());
            }
        }
        self::assertSame(['deletedBy' => 'editor@example.test'], $meta);
    }
}
