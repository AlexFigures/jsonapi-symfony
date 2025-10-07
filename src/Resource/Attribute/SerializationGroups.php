<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

/**
 * Атрибут для указания групп сериализации для атрибута ресурса.
 * 
 * Позволяет контролировать, когда атрибут доступен для чтения/записи:
 * - read: атрибут включается в ответ (GET, POST, PATCH)
 * - write: атрибут может быть изменён (POST, PATCH)
 * - create: атрибут может быть установлен только при создании (POST)
 * - update: атрибут может быть изменён только при обновлении (PATCH)
 * 
 * Примеры:
 * 
 * ```php
 * // Только для чтения (например, createdAt)
 * #[Attribute]
 * #[SerializationGroups(['read'])]
 * private \DateTimeInterface $createdAt;
 * 
 * // Только для записи (например, password)
 * #[Attribute]
 * #[SerializationGroups(['write'])]
 * private string $password;
 * 
 * // Можно установить только при создании
 * #[Attribute]
 * #[SerializationGroups(['read', 'create'])]
 * private string $slug;
 * 
 * // Обычный атрибут (чтение и запись)
 * #[Attribute]
 * #[SerializationGroups(['read', 'write'])]
 * private string $title;
 * ```
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class SerializationGroups
{
    /**
     * @param array<string> $groups Группы сериализации
     */
    public function __construct(
        public array $groups = [],
    ) {
    }

    public function isReadable(): bool
    {
        return in_array('read', $this->groups, true);
    }

    public function isWritable(): bool
    {
        return in_array('write', $this->groups, true);
    }

    public function isCreatable(): bool
    {
        return in_array('create', $this->groups, true);
    }

    public function isUpdatable(): bool
    {
        return in_array('update', $this->groups, true);
    }

    public function canRead(): bool
    {
        return $this->isReadable();
    }

    public function canWrite(bool $isCreate): bool
    {
        // Если есть группа 'write', можно писать всегда
        if ($this->isWritable()) {
            return true;
        }

        // Если создание и есть группа 'create'
        if ($isCreate && $this->isCreatable()) {
            return true;
        }

        // Если обновление и есть группа 'update'
        if (!$isCreate && $this->isUpdatable()) {
            return true;
        }

        return false;
    }
}

