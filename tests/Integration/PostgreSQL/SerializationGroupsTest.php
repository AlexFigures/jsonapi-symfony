<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\PostgreSQL;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\User;

final class SerializationGroupsTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    // ==================== Create (группа 'create') ====================

    public function testCreateAllowsWritableAttributes(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_doe',
                'email' => 'john@example.com',
                'password' => 'secret123',
                'slug' => 'john-doe',
            ],
        );

        $user = $this->persister->create('users', $changes);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('john_doe', $user->getUsername());
        self::assertSame('john@example.com', $user->getEmail());
        self::assertSame('secret123', $user->getPassword());
        self::assertSame('john-doe', $user->getSlug());
    }

    public function testCreateIgnoresReadOnlyAttributes(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_doe',
                'email' => 'john@example.com',
                'password' => 'secret123',
                'slug' => 'john-doe',
                // Попытка установить read-only атрибуты
                'createdAt' => new \DateTimeImmutable('2020-01-01'),
                'updatedAt' => new \DateTimeImmutable('2020-01-01'),
            ],
        );

        $user = $this->persister->create('users', $changes);

        // createdAt и updatedAt должны быть установлены автоматически, а не из запроса
        self::assertNotEquals('2020-01-01', $user->getCreatedAt()->format('Y-m-d'));
        self::assertNotEquals('2020-01-01', $user->getUpdatedAt()->format('Y-m-d'));
    }

    public function testCreateIgnoresUpdateOnlyAttributes(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_doe',
                'email' => 'john@example.com',
                'password' => 'secret123',
                'slug' => 'john-doe',
                // Попытка установить update-only атрибут
                'role' => 'admin',
            ],
        );

        $user = $this->persister->create('users', $changes);

        // role не должна быть установлена при создании
        self::assertSame('user', $user->getRole()); // Дефолтное значение
    }

    // ==================== Update (группа 'update') ====================

    public function testUpdateAllowsWritableAttributes(): void
    {
        // Создаём пользователя
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        // Обновляем
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_updated',
                'email' => 'john.updated@example.com',
                'password' => 'newsecret456',
            ],
        );

        $updated = $this->persister->update('users', 'user-1', $changes);

        self::assertSame('john_updated', $updated->getUsername());
        self::assertSame('john.updated@example.com', $updated->getEmail());
        self::assertSame('newsecret456', $updated->getPassword());
    }

    public function testUpdateIgnoresReadOnlyAttributes(): void
    {
        // Создаём пользователя
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();

        // Получаем createdAt после persist
        $originalCreatedAt = $user->getCreatedAt();
        $this->em->clear();

        // Попытка обновить read-only атрибуты
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_updated',
                'createdAt' => new \DateTimeImmutable('2020-01-01'),
                'updatedAt' => new \DateTimeImmutable('2020-01-01'),
            ],
        );

        $updated = $this->persister->update('users', 'user-1', $changes);

        // createdAt не должен измениться
        self::assertEquals(
            $originalCreatedAt->format('Y-m-d H:i:s'),
            $updated->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }

    public function testUpdateIgnoresCreateOnlyAttributes(): void
    {
        // Создаём пользователя
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        // Попытка обновить create-only атрибут
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_updated',
                'slug' => 'new-slug', // create-only!
            ],
        );

        $updated = $this->persister->update('users', 'user-1', $changes);

        // slug не должен измениться
        self::assertSame('john-doe', $updated->getSlug());
        // username должен измениться
        self::assertSame('john_updated', $updated->getUsername());
    }

    public function testUpdateAllowsUpdateOnlyAttributes(): void
    {
        // Создаём пользователя
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        // Обновляем update-only атрибут
        $changes = new ChangeSet(
            attributes: [
                'role' => 'admin',
            ],
        );

        $updated = $this->persister->update('users', 'user-1', $changes);

        // role должна измениться при обновлении
        self::assertSame('admin', $updated->getRole());
    }

    // ==================== Комбинированные тесты ====================

    public function testCreateAndUpdateRespectDifferentGroups(): void
    {
        // Создание: slug можно установить, role нельзя
        $createChanges = new ChangeSet(
            attributes: [
                'username' => 'john_doe',
                'email' => 'john@example.com',
                'password' => 'secret123',
                'slug' => 'john-doe',
                'role' => 'admin', // Будет проигнорировано
            ],
        );

        $user = $this->persister->create('users', $createChanges);
        $userId = $user->getId();

        self::assertSame('john-doe', $user->getSlug());
        self::assertSame('user', $user->getRole()); // Дефолтное значение

        $this->em->clear();

        // Обновление: slug нельзя изменить, role можно
        $updateChanges = new ChangeSet(
            attributes: [
                'slug' => 'new-slug', // Будет проигнорировано
                'role' => 'admin', // Будет применено
            ],
        );

        $updated = $this->persister->update('users', $userId, $updateChanges);

        self::assertSame('john-doe', $updated->getSlug()); // Не изменился
        self::assertSame('admin', $updated->getRole()); // Изменился
    }

    public function testPasswordIsWriteOnlyNeverReturned(): void
    {
        // Создаём пользователя
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_doe',
                'email' => 'john@example.com',
                'password' => 'secret123',
                'slug' => 'john-doe',
            ],
        );

        $user = $this->persister->create('users', $changes);

        // Пароль записан в БД
        self::assertSame('secret123', $user->getPassword());

        // Но в реальном API он не должен возвращаться в JSON
        // (это проверяется в DocumentBuilder, который использует isReadable())
        $metadata = $this->registry->getByType('users');
        $passwordAttribute = null;
        foreach ($metadata->attributes as $attr) {
            if ($attr->name === 'password') {
                $passwordAttribute = $attr;
                break;
            }
        }

        self::assertNotNull($passwordAttribute);
        self::assertFalse($passwordAttribute->isReadable()); // Не читается!
    }
}

