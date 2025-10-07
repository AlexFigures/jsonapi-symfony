<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Persister;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Doctrine Persister с автоматической валидацией через Symfony Validator.
 * 
 * Расширяет GenericDoctrinePersister, добавляя валидацию перед persist().
 * 
 * Использует Symfony Validator constraints на Entity:
 * - #[Assert\NotBlank]
 * - #[Assert\Length]
 * - #[Assert\Email]
 * - и т.д.
 */
final class ValidatingDoctrinePersister implements ResourcePersister
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly ValidatorInterface $validator,
        private readonly ConstraintViolationMapper $violationMapper,
    ) {
    }

    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->class;

        // Проверка конфликта ID
        if ($clientId !== null && $this->em->find($entityClass, $clientId)) {
            throw new ConflictException(
                sprintf('Resource "%s" with id "%s" already exists.', $type, $clientId)
            );
        }

        // Создаем новую сущность
        $classMetadata = $this->em->getClassMetadata($entityClass);
        $entity = $classMetadata->newInstance();

        $idPath = $metadata->idPropertyPath ?? 'id';

        if ($clientId !== null) {
            $this->accessor->setValue($entity, $idPath, $clientId);
        } elseif ($classMetadata->isIdentifierNatural()) {
            $this->accessor->setValue($entity, $idPath, Uuid::v4()->toRfc4122());
        }

        // Применяем атрибуты с учётом групп сериализации
        $this->applyAttributes($entity, $metadata, $changes, true);

        // Валидация перед persist
        $this->validate($entity, $type);

        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    public function update(string $type, string $id, ChangeSet $changes): object
    {
        $metadata = $this->registry->getByType($type);
        $entity = $this->em->find($metadata->class, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        // Применяем атрибуты с учётом групп сериализации
        $this->applyAttributes($entity, $metadata, $changes, false);

        // Валидация перед flush
        $this->validate($entity, $type);

        $this->em->flush();

        return $entity;
    }

    public function delete(string $type, string $id): void
    {
        $metadata = $this->registry->getByType($type);
        $entity = $this->em->find($metadata->class, $id);

        if ($entity === null) {
            throw new NotFoundException(
                sprintf('Resource "%s" with id "%s" not found.', $type, $id)
            );
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    private function applyAttributes(
        object $entity,
        \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        ChangeSet $changes,
        bool $isCreate
    ): void {
        foreach ($changes->attributes as $path => $value) {
            // Проверяем, можно ли записать этот атрибут
            $attributeMetadata = $this->findAttributeMetadata($metadata, $path);

            if ($attributeMetadata !== null && !$attributeMetadata->isWritable($isCreate)) {
                // Пропускаем атрибуты, которые нельзя записать
                continue;
            }

            $this->accessor->setValue($entity, $path, $value);
        }
    }

    private function findAttributeMetadata(
        \JsonApi\Symfony\Resource\Metadata\ResourceMetadata $metadata,
        string $path
    ): ?\JsonApi\Symfony\Resource\Metadata\AttributeMetadata {
        foreach ($metadata->attributes as $attribute) {
            if ($attribute->propertyPath === $path || $attribute->name === $path) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Валидирует сущность и выбрасывает исключение при ошибках.
     *
     * @throws \JsonApi\Symfony\Http\Exception\ValidationException
     */
    private function validate(object $entity, string $type): void
    {
        $violations = $this->validator->validate($entity);

        if (count($violations) > 0) {
            // ConstraintViolationMapper преобразует violations в JSON:API errors
            throw $this->violationMapper->mapToException($type, $violations);
        }
    }
}
