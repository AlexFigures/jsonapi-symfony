<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Doctrine\DQL;

use AlexFigures\Symfony\Bridge\Doctrine\DQL\ILikeFunction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use PHPUnit\Framework\TestCase;

final class ILikeFunctionTest extends TestCase
{
    public function testGetSqlWithPostgreSQL(): void
    {
        $function = new ILikeFunction('ILIKE');

        // Mock PathExpression for field
        $field = $this->createMock(PathExpression::class);

        // Mock PathExpression for pattern
        $pattern = $this->createMock(PathExpression::class);

        // Use reflection to set private properties
        $reflection = new \ReflectionClass($function);

        $fieldProperty = $reflection->getProperty('field');
        $fieldProperty->setValue($function, $field);

        $patternProperty = $reflection->getProperty('pattern');
        $patternProperty->setValue($function, $pattern);

        // Mock SqlWalker
        $sqlWalker = $this->createMock(SqlWalker::class);

        // Mock Connection
        $connection = $this->createMock(Connection::class);
        $platform = new PostgreSQLPlatform();

        $connection->method('getDatabasePlatform')->willReturn($platform);
        $sqlWalker->method('getConnection')->willReturn($connection);

        // Mock dispatch calls
        $sqlWalker->method('walkPathExpression')
            ->willReturnOnConsecutiveCalls('e.name', ':param');

        // Field and pattern should dispatch to SQL
        $field->method('dispatch')->with($sqlWalker)->willReturn('e.name');
        $pattern->method('dispatch')->with($sqlWalker)->willReturn(':param');

        $sql = $function->getSql($sqlWalker);

        self::assertStringContainsString('ILIKE', $sql);
        self::assertStringContainsString('e.name', $sql);
        self::assertStringContainsString(':param', $sql);
    }

    public function testGetSqlWithMySQL(): void
    {
        $function = new ILikeFunction('ILIKE');

        // Mock PathExpression for field
        $field = $this->createMock(PathExpression::class);

        // Mock PathExpression for pattern
        $pattern = $this->createMock(PathExpression::class);

        // Use reflection to set private properties
        $reflection = new \ReflectionClass($function);

        $fieldProperty = $reflection->getProperty('field');
        $fieldProperty->setValue($function, $field);

        $patternProperty = $reflection->getProperty('pattern');
        $patternProperty->setValue($function, $pattern);

        // Mock SqlWalker
        $sqlWalker = $this->createMock(SqlWalker::class);

        // Mock Connection
        $connection = $this->createMock(Connection::class);
        $platform = new MySQLPlatform();

        $connection->method('getDatabasePlatform')->willReturn($platform);
        $sqlWalker->method('getConnection')->willReturn($connection);

        // Field and pattern should dispatch to SQL
        $field->method('dispatch')->with($sqlWalker)->willReturn('e.name');
        $pattern->method('dispatch')->with($sqlWalker)->willReturn(':param');

        $sql = $function->getSql($sqlWalker);

        // Should use LOWER() for MySQL
        self::assertStringContainsString('LOWER(e.name)', $sql);
        self::assertStringContainsString('LIKE', $sql);
        self::assertStringContainsString('LOWER(:param)', $sql);
        self::assertStringNotContainsString('ILIKE', $sql);
    }
}
