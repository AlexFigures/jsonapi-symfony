<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Atomic\Validation;

use AlexFigures\JsonApi\Atomic\AtomicConfig;
use AlexFigures\JsonApi\Atomic\Lid\LidRegistry;
use AlexFigures\JsonApi\Atomic\Operation;
use AlexFigures\JsonApi\Atomic\Ref;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Resource\Metadata\RelationshipMetadata;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;

/** @internal */
final readonly class AtomicValidator
{
    private const ALLOWED_OPS = ['add', 'update', 'remove'];

    public function __construct(
        private AtomicConfig $config,
        private ResourceRegistryInterface $registry,
        private ErrorMapper $errors,
    ) {
    }

    /**
     * @param list<Operation> $operations
     *
     * @return array{0: list<Operation>, 1: LidRegistry}
     */
    public function validate(array $operations, ?\Symfony\Component\HttpFoundation\Request $request = null): array
    {
        $lidRegistry = new LidRegistry();
        $validated = [];

        foreach ($operations as $operation) {
            $validated[] = $this->validateOperation($operation, $lidRegistry, $request);
        }

        return [$validated, $lidRegistry];
    }

    private function validateOperation(Operation $operation, LidRegistry $lids, ?\Symfony\Component\HttpFoundation\Request $request): Operation
    {
        if (!in_array($operation->op, self::ALLOWED_OPS, true)) {
            throw new BadRequestException('Unsupported atomic operation.', [
                $this->errors->invalidPointer($operation->pointer . '/op', sprintf('Unknown operation "%s".', $operation->op)),
            ]);
        }

        if ($operation->ref !== null && $operation->href !== null) {
            throw new BadRequestException('Invalid target specification.', [
                $this->errors->invalidPointer($operation->pointer, 'Operations MUST specify either "ref" or "href", but not both.'),
            ]);
        }

        $ref = $operation->ref;
        if ($ref === null && $operation->href === null && in_array($operation->op, ['add', 'update'], true)
            && is_array($operation->data) && is_string($operation->data['type'] ?? null)) {
            foreach (['id', 'lid'] as $member) {
                if (isset($operation->data[$member]) && (!is_string($operation->data[$member]) || $operation->data[$member] === '')) {
                    throw new BadRequestException('Invalid resource identifier.');
                }
            }
            $ref = new Ref($operation->data['type'], is_string($operation->data['id'] ?? null) ? $operation->data['id'] : null, is_string($operation->data['lid'] ?? null) ? $operation->data['lid'] : null, null);
        }
        if ($ref === null) {
            if ($operation->href === null) {
                throw new BadRequestException('Missing operation target.', [$this->errors->invalidPointer($operation->pointer, 'The operation needs a target or an identifying resource object.')]);
            }
            if (!$this->config->allowHref) {
                throw new BadRequestException('Href targets are disabled.');
            }
            $ref = $this->refFromHref($operation->href, $operation->pointer . '/href', $request);
        }

        $typePointer = $operation->ref !== null ? $operation->pointer . '/ref/type' : $operation->pointer . ($operation->href !== null ? '/href' : '/data/type');

        if (!$this->registry->hasType($ref->type)) {
            throw new BadRequestException('Unknown resource type.', [
                $this->errors->invalidPointer(
                    $typePointer,
                    sprintf('Resource type "%s" is not recognised.', $ref->type)
                ),
            ]);
        }

        if ($ref->lid !== null && $lids->has($ref->lid) && $lids->getType($ref->lid) !== $ref->type) {
            throw new BadRequestException('Local identifier type mismatch.', [$this->errors->invalidPointer($operation->pointer . '/ref/type', 'The lid belongs to a different resource type.')]);
        }
        $metadata = $this->registry->getByType($ref->type);
        $policyOperation = $ref->relationship !== null ? \AlexFigures\JsonApi\Resource\Definition\ResourceOperation::UPDATE : match ($operation->op) {
            'add' => \AlexFigures\JsonApi\Resource\Definition\ResourceOperation::CREATE,
            'update' => \AlexFigures\JsonApi\Resource\Definition\ResourceOperation::UPDATE,
            'remove' => \AlexFigures\JsonApi\Resource\Definition\ResourceOperation::DELETE,
        };
        (new \AlexFigures\JsonApi\Http\Controller\Support\OperationValidator($this->errors))->assertAtomicAllowed($policyOperation, $metadata->allowedOperations);

        if ($ref->relationship !== null) {
            $relationship = $metadata->relationships[$ref->relationship] ?? null;
            if ($relationship === null) {
                throw new BadRequestException('Unknown relationship.', [
                    $this->errors->invalidPointer($operation->pointer . '/ref/relationship', sprintf('Relationship "%s" is not defined for resource "%s".', $ref->relationship, $ref->type)),
                ]);
            }

            $this->validateRelationshipData($operation, $relationship);

            return new Operation($operation->op, $ref, $operation->href, $operation->data, $operation->meta, $operation->pointer);
        }

        $this->validateResourceTarget($operation, $ref, $lids);

        return new Operation($operation->op, $ref, $operation->href, $operation->data, $operation->meta, $operation->pointer);
    }

    private function validateResourceTarget(Operation $operation, Ref $ref, LidRegistry $lids): void
    {
        if (is_array($operation->data)) {
            $data = $operation->data;
            if (isset($data['id'], $data['lid'])) {
                throw new BadRequestException('Identifiers cannot contain both id and lid.', [$this->errors->invalidPointer($operation->pointer . '/data', 'Use either id or lid.')]);
            }
            foreach (['id', 'lid'] as $member) {
                if (isset($data[$member]) && (!is_string($data[$member]) || $data[$member] === '')) {
                    throw new BadRequestException('Invalid identifier.', [$this->errors->invalidPointer($operation->pointer . '/data/' . $member, 'Identifiers must be non-empty strings.')]);
                }
            }
            if ($operation->op === 'update' && isset($data['id']) && $ref->id !== null && $data['id'] !== $ref->id) {
                throw new BadRequestException('Identifier mismatch.', [$this->errors->invalidPointer($operation->pointer . '/data/id', 'The data identifier must match the target.')]);
            }
        }
        if ($operation->op === 'add') {
            $this->assertDataIsResource($operation);
            $data = $operation->data;
            \assert(is_array($data));
            $dataType = $data['type'] ?? null;
            if (!is_string($dataType) || $dataType === '') {
                throw new BadRequestException('Missing resource type.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', 'Resource objects MUST specify a type.'),
                ]);
            }

            if ($dataType !== $ref->type) {
                throw new BadRequestException('Type mismatch.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', sprintf('Resource type must be "%s", got "%s".', $ref->type, $dataType)),
                ]);
            }

            if (isset($data['lid']) && is_string($data['lid']) && $data['lid'] !== '') {
                $lids->register($data['lid'], $ref->type);
            }

            return;
        }

        if (!$ref->hasIdentifier()) {
            throw new BadRequestException('Missing identifier.', [
                $this->errors->invalidPointer($operation->pointer . '/ref', 'Resource operations other than "add" MUST specify an identifier.'),
            ]);
        }

        if ($operation->op === 'update') {
            $this->assertDataIsResource($operation);
            $data = $operation->data;
            \assert(is_array($data));
            $dataType = $data['type'] ?? null;
            if ($dataType !== null && (!is_string($dataType) || $dataType === '')) {
                throw new BadRequestException('Invalid resource type.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', 'When present, the "type" member MUST be a non-empty string.'),
                ]);
            }

            if (is_string($dataType) && $dataType !== $ref->type) {
                throw new BadRequestException('Type mismatch.', [
                    $this->errors->invalidPointer($operation->pointer . '/data/type', sprintf('Resource type must be "%s", got "%s".', $ref->type, $dataType)),
                ]);
            }
        }
    }

    private function assertDataIsResource(Operation $operation): void
    {
        if (!is_array($operation->data) || array_is_list($operation->data)) {
            throw new BadRequestException('Resource data must be an object.', [
                $this->errors->invalidPointer($operation->pointer . '/data', 'The "data" member MUST be a resource object.'),
            ]);
        }
    }

    private function validateRelationshipData(Operation $operation, RelationshipMetadata $relationship): void
    {
        $targetType = $relationship->targetType;
        if ($targetType === null) {
            throw new BadRequestException('Relationship target type is not configured.', [
                $this->errors->invalidPointer($operation->pointer . '/ref/relationship', sprintf('Relationship "%s" is missing a target type.', $relationship->name)),
            ]);
        }

        if ($relationship->toMany) {
            if ($operation->op === 'remove' || $operation->op === 'add' || $operation->op === 'update') {
                if (!is_array($operation->data) || !array_is_list($operation->data)) {
                    throw new BadRequestException('Relationship data must be a list.', [
                        $this->errors->invalidPointer($operation->pointer . '/data', 'Relationship data for to-many relationships MUST be an array of resource identifiers.'),
                    ]);
                }

                foreach ($operation->data as $index => $identifier) {
                    $this->validateResourceIdentifier($identifier, $targetType, sprintf('%s/data/%d', $operation->pointer, $index));
                }

                return;
            }
        } else {
            if ($operation->op !== 'update') {
                throw new BadRequestException('Invalid operation for to-one relationship.', [
                    $this->errors->invalidPointer($operation->pointer . '/op', 'Only the "update" operation is allowed for to-one relationships.'),
                ]);
            }

            if ($operation->data === null) {
                return;
            }

            $this->validateResourceIdentifier($operation->data, $targetType, $operation->pointer . '/data');
        }
    }

    private function validateResourceIdentifier(mixed $identifier, string $expectedType, string $pointer): void
    {
        \AlexFigures\JsonApi\Http\Write\RelationshipIdentifierValidator::validate($identifier, $expectedType, $pointer, $this->errors, true, 400);
    }

    private function refFromHref(string $href, string $pointer, ?\Symfony\Component\HttpFoundation\Request $request = null): Ref
    {
        $parts = parse_url($href);
        if ($parts === false || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new BadRequestException('Invalid href.', [$this->errors->invalidPointer($pointer, 'Unsupported URI reference.')]);
        }
        if (isset($parts['host']) || isset($parts['scheme'])) {
            if ($request === null || !isset($parts['host']) || strtolower($parts['host']) !== strtolower($request->getHost())
                || ($parts['scheme'] ?? $request->getScheme()) !== $request->getScheme()
                || ($parts['port'] ?? (($parts['scheme'] ?? $request->getScheme()) === 'https' ? 443 : 80)) !== $request->getPort()) {
                throw new BadRequestException('External href not allowed.', [$this->errors->invalidPointer($pointer, 'The target must have the same origin as the request.')]);
            }
        }
        $href = $parts['path'] ?? '';
        if (!str_starts_with($href, '/')) {
            $href = rtrim($this->config->routePrefix, '/') . '/' . $href;
        }
        if (str_contains($href, '//') || str_ends_with($href, '/')) {
            throw new BadRequestException('Invalid href path.');
        }
        // Resolve dot segments, rejecting encoded separators before matching the complete target.
        $resolved = [];
        foreach (explode('/', $href) as $segment) {
            $segment = rawurldecode($segment);
            if ($segment === '..') {
                array_pop($resolved);
            } elseif ($segment !== '.' && $segment !== '') {
                if (str_contains($segment, '/') || str_contains($segment, '\\')) {
                    throw new BadRequestException('Invalid href path segment.');
                }
                $resolved[] = $segment;
            }
        }
        $href = '/' . implode('/', $resolved);
        $routePrefix = rtrim($this->config->routePrefix, '/');
        if ($routePrefix === '') {
            $routePrefix = '/';
        }

        if ($routePrefix !== '/' && !str_starts_with($href, $routePrefix . '/')) {
            throw new BadRequestException('Invalid href.', [
                $this->errors->invalidPointer($pointer, sprintf('The "href" member MUST start with "%s".', $routePrefix)),
            ]);
        }

        $path = $routePrefix === '/' ? substr($href, 1) : substr($href, strlen($routePrefix) + 1);
        $segments = $path === '' ? [] : explode('/', $path);

        if ($segments === []) {
            throw new BadRequestException('Invalid href.', [
                $this->errors->invalidPointer($pointer, 'Unable to resolve type from href.'),
            ]);
        }

        $type = array_shift($segments);
        $id = null;
        $relationship = null;

        if ($segments !== []) {
            $id = array_shift($segments);
        }

        if ($segments !== [] && $segments[0] === 'relationships') {
            array_shift($segments);
            $relationship = array_shift($segments);
            if ($relationship === null) {
                throw new BadRequestException('A relationship href must include its name.', [$this->errors->invalidPointer($pointer, 'Missing relationship name.')]);
            }
        }

        if ($segments !== [] || $type === '') {
            throw new BadRequestException('Invalid href target.', [$this->errors->invalidPointer($pointer, 'All href path segments must identify a supported target.')]);
        }
        return new Ref($type, $id, null, $relationship);
    }
}
