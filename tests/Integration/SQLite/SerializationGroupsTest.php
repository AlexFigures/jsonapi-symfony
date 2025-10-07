<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\SQLite;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\User;

final class SerializationGroupsTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_SQLITE'] ?? 'sqlite:///:memory:';
    }

    protected function getPlatform(): string
    {
        return 'sqlite';
    }

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
}

