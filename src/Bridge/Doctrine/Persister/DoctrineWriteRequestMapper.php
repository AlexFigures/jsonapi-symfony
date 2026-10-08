<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Persister;

use AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Resource\Mapper\WriteMapperInterface;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Resource\Write\WriteContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @internal Operation-specific input is validated before touching a managed entity. */
final readonly class DoctrineWriteRequestMapper
{
    public function __construct(
        private SerializerEntityInstantiator $instantiator,
        private ValidatorInterface $validator,
        private ConstraintViolationMapper $errors,
        private WriteMapperInterface $mapper,
        private RequestStack $requests,
    ) {
    }

    public function map(ResourceMetadata $metadata, ChangeSet $changes, string $operation, ?object $entity = null): ?object
    {
        $request = $this->requests->getCurrentRequest();
        $context = $request === null ? null : ProfileContext::fromRequest($request)?->forType($metadata->type);
        $definition = $metadata->getDefinition($context);
        $class = $definition->writeRequests[$operation] ?? null;
        if ($class === null) {
            return null;
        }
        $options = $metadata->denormalizationContext;
        // Entity serializer groups do not define the independent input DTO's fields.
        unset($options[AbstractNormalizer::GROUPS], $options[AbstractNormalizer::OBJECT_TO_POPULATE]);
        $options[AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES] = false;
        $options[AbstractNormalizer::COLLECT_DENORMALIZATION_ERRORS] = true;
        try {
            $dto = $this->instantiator->denormalizer()->denormalize($this->instantiator->prepareDataForDenormalization($changes, $metadata), $class, null, $options);
        } catch (ExceptionInterface|\ValueError|\InvalidArgumentException $error) {
            throw $this->errors->mapDenormErrors($metadata->type, $error);
        }
        $violations = $this->validator->validate($dto, null, $metadata->getDenormalizationGroups());
        if (count($violations) > 0) {
            throw $this->errors->mapToException($metadata->type, $violations);
        }
        $writeContext = new WriteContext(options: ['operation' => $operation, 'relationships' => $changes->relationships]);
        $entity ??= $this->mapper->instantiate($definition, $dto, $writeContext);
        if (!$entity instanceof $definition->dataClass) {
            throw new \LogicException('WriteMapper must return the configured resource data class.');
        }
        $this->mapper->apply($entity, $dto, $definition, $writeContext);
        return $entity;
    }
}
