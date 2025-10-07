<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Instantiator;

use Doctrine\ORM\EntityManagerInterface;
use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Инстанциатор сущностей, использующий Symfony Serializer.
 * 
 * Это правильный подход, используемый в API Platform:
 * - Использует ObjectNormalizer из Symfony Serializer
 * - Автоматически обрабатывает конструкторы с параметрами
 * - Поддерживает все возможности Symfony Serializer
 * - Не требует написания собственной логики рефлексии
 * 
 * Преимущества:
 * - Меньше кода
 * - Лучшая поддержка типов
 * - Автоматическая обработка конструкторов
 * - Совместимость с API Platform
 * - Поддержка кастомных денормализаторов
 * 
 * Как это работает:
 * 1. Преобразует ChangeSet в массив данных
 * 2. Использует Serializer::denormalize() для создания объекта
 * 3. Serializer автоматически вызывает конструктор с параметрами
 * 4. Оставшиеся свойства устанавливаются через сеттеры/свойства
 */
final class SerializerEntityInstantiator
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAccessorInterface $accessor,
    ) {
        // Создаем PropertyInfoExtractor для определения типов
        // Используем только ReflectionExtractor (не требует phpdocumentor/reflection-docblock)
        $reflectionExtractor = new ReflectionExtractor();

        $propertyInfo = new PropertyInfoExtractor(
            [$reflectionExtractor],  // listExtractors
            [$reflectionExtractor],  // typeExtractors
            [],                      // descriptionExtractors (не нужны)
            [$reflectionExtractor],  // accessExtractors
            [$reflectionExtractor]   // initializableExtractors
        );

        // Создаем ObjectNormalizer с поддержкой конструкторов
        $normalizer = new ObjectNormalizer(
            null, // ClassMetadataFactory (не нужен для базового использования)
            null, // NameConverter (не нужен)
            $this->accessor, // PropertyAccessor для установки свойств
            $propertyInfo, // PropertyInfo для определения типов
            null, // ClassDiscriminator (не нужен)
            null, // ObjectClassResolver (не нужен)
        );

        // Создаем Serializer с ObjectNormalizer
        $this->serializer = new Serializer([$normalizer]);
    }

    /**
     * Создает экземпляр сущности, используя Symfony Serializer.
     *
     * @param bool $isCreate true для POST (create), false для PATCH (update)
     * @return array{entity: object, remainingChanges: ChangeSet}
     */
    public function instantiate(
        string $entityClass,
        ResourceMetadata $metadata,
        ChangeSet $changes,
        bool $isCreate = true,
    ): array {
        $reflection = new \ReflectionClass($entityClass);
        $constructor = $reflection->getConstructor();

        // Если конструктора нет или он без параметров, используем стандартный способ
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $classMetadata = $this->em->getClassMetadata($entityClass);
            $entity = $classMetadata->newInstance();

            return [
                'entity' => $entity,
                'remainingChanges' => $changes,
            ];
        }

        // Фильтруем атрибуты по SerializationGroups
        $filteredChanges = $this->filterBySerializationGroups($changes, $metadata, $isCreate);

        // Подготавливаем данные для денормализации
        $data = $this->prepareDataForDenormalization($filteredChanges, $metadata);

        // Используем Symfony Serializer для создания объекта
        // Он автоматически вызовет конструктор с правильными параметрами!
        $entity = $this->serializer->denormalize(
            $data,
            $entityClass,
            null,
            [
                // Разрешаем частичную денормализацию (не все свойства обязательны)
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
                // Игнорируем свойства, которые нельзя установить
                AbstractNormalizer::IGNORED_ATTRIBUTES => [],
            ]
        );

        // Определяем, какие атрибуты были использованы конструктором
        $usedAttributes = $this->getConstructorParameters($constructor, $filteredChanges, $metadata);

        // Создаем новый ChangeSet без использованных атрибутов
        $remainingAttributes = [];
        foreach ($filteredChanges->attributes as $path => $value) {
            if (!in_array($path, $usedAttributes, true)) {
                $remainingAttributes[$path] = $value;
            }
        }

        return [
            'entity' => $entity,
            'remainingChanges' => new ChangeSet($remainingAttributes),
        ];
    }

    /**
     * Подготавливает данные из ChangeSet для Symfony Serializer.
     * 
     * @return array<string, mixed>
     */
    private function prepareDataForDenormalization(
        ChangeSet $changes,
        ResourceMetadata $metadata
    ): array {
        $data = [];

        foreach ($changes->attributes as $path => $value) {
            // Ищем метаданные атрибута по property path (аналогично filterBySerializationGroups)
            $attributeMetadata = $this->findAttributeMetadata($metadata, $path);
            $propertyPath = $attributeMetadata?->propertyPath ?? $path;

            $data[$propertyPath] = $value;
        }

        return $data;
    }

    /**
     * Фильтрует атрибуты по SerializationGroups.
     *
     * Учитывает группы 'write', 'create', 'update':
     * - 'write': можно записывать всегда (POST и PATCH)
     * - 'create': можно записывать только при создании (POST)
     * - 'update': можно записывать только при обновлении (PATCH)
     */
    private function filterBySerializationGroups(
        ChangeSet $changes,
        ResourceMetadata $metadata,
        bool $isCreate
    ): ChangeSet {
        $filteredAttributes = [];

        foreach ($changes->attributes as $path => $value) {
            // Ищем метаданные атрибута по property path (аналогично GenericDoctrinePersister)
            $attributeMetadata = $this->findAttributeMetadata($metadata, $path);

            // Если метаданных нет, пропускаем атрибут (по умолчанию разрешаем)
            if ($attributeMetadata === null) {
                $filteredAttributes[$path] = $value;
                continue;
            }

            // Проверяем, можно ли записывать этот атрибут
            if ($attributeMetadata->isWritable($isCreate)) {
                $filteredAttributes[$path] = $value;
            }
            // Если атрибут не доступен для записи, он будет проигнорирован
        }

        return new ChangeSet($filteredAttributes);
    }

    /**
     * Находит метаданные атрибута по property path или имени.
     *
     * Это критично для безопасности: когда атрибут переименован через #[Attribute(name: 'new-name')],
     * метаданные индексируются по новому имени, но ChangeSet содержит property path.
     * Без правильного поиска атрибуты с SerializationGroups могут быть неправильно обработаны.
     */
    private function findAttributeMetadata(
        ResourceMetadata $metadata,
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
     * Получает список параметров конструктора, которые были использованы.
     *
     * @return list<string>
     */
    private function getConstructorParameters(
        \ReflectionMethod $constructor,
        ChangeSet $changes,
        ResourceMetadata $metadata
    ): array {
        $usedAttributes = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            // Проверяем, есть ли этот параметр в ChangeSet
            if (isset($changes->attributes[$paramName])) {
                $usedAttributes[] = $paramName;
                continue;
            }

            // Проверяем через propertyPath в метаданных
            foreach ($metadata->attributes as $attributeName => $attributeMetadata) {
                $propertyPath = $attributeMetadata->propertyPath ?? $attributeName;

                if ($propertyPath === $paramName && isset($changes->attributes[$attributeName])) {
                    $usedAttributes[] = $attributeName;
                    break;
                }
            }
        }

        return $usedAttributes;
    }
}

