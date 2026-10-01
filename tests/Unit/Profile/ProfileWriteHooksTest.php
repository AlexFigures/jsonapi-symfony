<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Profile;

use AlexFigures\Symfony\Bridge\Doctrine\Profile\ProfileWriteHooks;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Profile\Hook\WriteHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class ProfileWriteHooksTest extends TestCase
{
    public function testHooksReceiveInputAndOnlyTheirChangesAreApplied(): void
    {
        $hook = new class () implements WriteHook {
            public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changes): void
            {
                $changes->attributes['display-name'] = 'Server ' . $changes->attributes['display-name'];
            }
            public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changes): void
            {
                $this->onBeforeCreate($context, $type, $changes);
            }
            public function onBeforeDelete(ProfileContext $context, string $type, string $id): void
            {
            }
        };
        $request = Request::create('/api/records', 'POST');
        $stack = new RequestStack();
        $stack->push($request);
        ProfileContext::store($request, new ProfileContext([], ['records' => [new FakeProfile('urn:test:input', [$hook])]]));
        $metadata = new ResourceMetadata(type: 'records', class: \stdClass::class, attributes: ['display-name' => new AttributeMetadata(name: 'display-name', propertyPath: 'name')], relationships: []);
        $entity = (object) ['name' => 'Client'];
        $input = new ChangeSet(['display-name' => 'Client']);
        (new ProfileWriteHooks($stack, PropertyAccess::createPropertyAccessor()))->apply($entity, $metadata, true, $input);
        self::assertSame('Server Client', $entity->name);
        self::assertSame('Client', $input->attributes['display-name']);
    }
}
