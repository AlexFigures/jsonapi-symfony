<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Filter\Operator;

use AlexFigures\Symfony\Filter\Operator\ILikeOperator;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AlexFigures\Symfony\Filter\Operator\ILikeOperator
 */
final class ILikeOperatorTest extends TestCase
{
    private ILikeOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new ILikeOperator();
    }

    public function testName(): void
    {
        self::assertSame('ilike', $this->operator->name());
    }

    public function testCompileWithPostgreSQL(): void
    {
        $platform = new PostgreSQLPlatform();
        $expression = $this->operator->compile('e', 'e.name', ['laptop'], $platform);

        // Should use ILIKE() DQL function
        self::assertStringContainsString('ILIKE(e.name,', $expression->dql);
        self::assertStringContainsString('= true', $expression->dql);
        self::assertCount(1, $expression->parameters);

        // Check that one of the parameters contains the pattern
        $hasPattern = false;
        foreach ($expression->parameters as $value) {
            if ($value === '%laptop%') {
                $hasPattern = true;
                break;
            }
        }
        self::assertTrue($hasPattern, 'Expected to find %laptop% in parameters');
    }

    public function testCompileWithMySQL(): void
    {
        $platform = new MySQLPlatform();
        $expression = $this->operator->compile('e', 'e.name', ['laptop'], $platform);

        // Should use ILIKE() DQL function (same for all databases)
        self::assertStringContainsString('ILIKE(e.name,', $expression->dql);
        self::assertStringContainsString('= true', $expression->dql);
        self::assertCount(1, $expression->parameters);

        // Check that one of the parameters contains the pattern
        $hasPattern = false;
        foreach ($expression->parameters as $value) {
            if ($value === '%laptop%') {
                $hasPattern = true;
                break;
            }
        }
        self::assertTrue($hasPattern, 'Expected to find %laptop% in parameters');
    }

    public function testCompileWithEmptyValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ILikeOperator requires at least one value.');

        $platform = new PostgreSQLPlatform();
        $this->operator->compile('e', 'e.name', [], $platform);
    }

    public function testCompileWithNonStringValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ILikeOperator requires string-compatible value.');

        $platform = new PostgreSQLPlatform();
        $this->operator->compile('e', 'e.name', [['invalid']], $platform);
    }

    public function testCompileWithStringableValue(): void
    {
        $platform = new PostgreSQLPlatform();
        $stringable = new class () {
            public function __toString(): string
            {
                return 'test';
            }
        };

        $expression = $this->operator->compile('e', 'e.name', [$stringable], $platform);

        self::assertStringContainsString('ILIKE(e.name,', $expression->dql);

        // Check that one of the parameters contains the pattern
        $hasPattern = false;
        foreach ($expression->parameters as $value) {
            if ($value === '%test%') {
                $hasPattern = true;
                break;
            }
        }
        self::assertTrue($hasPattern, 'Expected to find %test% in parameters');
    }
}
