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

    // ==================== Create (group 'create') ====================

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
                // Attempt to set read-only attributes
                'createdAt' => new \DateTimeImmutable('2020-01-01'),
                'updatedAt' => new \DateTimeImmutable('2020-01-01'),
            ],
        );

        $user = $this->persister->create('users', $changes);

        // createdAt and updatedAt should be set automatically, not from request
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
                // Attempt to set update-only attribute
                'role' => 'admin',
            ],
        );

        $user = $this->persister->create('users', $changes);

        // role should not be set during creation
        self::assertSame('user', $user->getRole()); // Default value
    }

    // ==================== Update group ('update') ====================

    public function testUpdateAllowsWritableAttributes(): void
    {
        // Create a user
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        // Update the entity
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
        // Create a user
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();

        // Capture createdAt after persisting
        $originalCreatedAt = $user->getCreatedAt();
        $this->em->clear();

        // Attempt to update read-only attributes
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_updated',
                'createdAt' => new \DateTimeImmutable('2020-01-01'),
                'updatedAt' => new \DateTimeImmutable('2020-01-01'),
            ],
        );

        $updated = $this->persister->update('users', 'user-1', $changes);

        // createdAt must remain unchanged
        self::assertEquals(
            $originalCreatedAt->format('Y-m-d H:i:s'),
            $updated->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }

    public function testUpdateIgnoresCreateOnlyAttributes(): void
    {
        // Create a user
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        // Attempt to update a create-only attribute
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_updated',
                'slug' => 'new-slug', // create-only!
            ],
        );

        $updated = $this->persister->update('users', 'user-1', $changes);

        // slug must remain unchanged
        self::assertSame('john-doe', $updated->getSlug());
        // username should change
        self::assertSame('john_updated', $updated->getUsername());
    }

    public function testUpdateAllowsUpdateOnlyAttributes(): void
    {
        // Create a user
        $user = new User();
        $user->setId('user-1');
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setPassword('secret123');
        $user->setSlug('john-doe');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        // Update an update-only attribute
        $changes = new ChangeSet(
            attributes: [
                'role' => 'admin',
            ],
        );

        $updated = $this->persister->update('users', 'user-1', $changes);

        // role should change during update
        self::assertSame('admin', $updated->getRole());
    }

    // ==================== Combined tests ====================

    public function testCreateAndUpdateRespectDifferentGroups(): void
    {
        // Creation: slug is allowed, role is not
        $createChanges = new ChangeSet(
            attributes: [
                'username' => 'john_doe',
                'email' => 'john@example.com',
                'password' => 'secret123',
                'slug' => 'john-doe',
                'role' => 'admin', // Will be ignored
            ],
        );

        $user = $this->persister->create('users', $createChanges);
        $userId = $user->getId();

        self::assertSame('john-doe', $user->getSlug());
        self::assertSame('user', $user->getRole()); // Default value

        $this->em->clear();

        // Update: slug cannot change, role can
        $updateChanges = new ChangeSet(
            attributes: [
                'slug' => 'new-slug', // Will be ignored
                'role' => 'admin', // Will be applied
            ],
        );

        $updated = $this->persister->update('users', $userId, $updateChanges);

        self::assertSame('john-doe', $updated->getSlug()); // Unchanged
        self::assertSame('admin', $updated->getRole()); // Updated
    }

    public function testPasswordIsWriteOnlyNeverReturned(): void
    {
        // Create a user
        $changes = new ChangeSet(
            attributes: [
                'username' => 'john_doe',
                'email' => 'john@example.com',
                'password' => 'secret123',
                'slug' => 'john-doe',
            ],
        );

        $user = $this->persister->create('users', $changes);

        // Password persisted in the database
        self::assertSame('secret123', $user->getPassword());

        // It must not be returned in JSON responses
        // (enforced by DocumentBuilder via isReadable())
        $metadata = $this->registry->getByType('users');
        $passwordAttribute = null;
        foreach ($metadata->attributes as $attr) {
            if ($attr->name === 'password') {
                $passwordAttribute = $attr;
                break;
            }
        }

        self::assertNotNull($passwordAttribute);
        self::assertFalse($passwordAttribute->isReadable()); // Not readable!
    }
}
