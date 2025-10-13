<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Filter\Operator;

use AlexFigures\Symfony\Filter\Operator\NotInOperator;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

final class NotInOperatorTest extends TestCase
{
    public function testCompileBuildsNotInExpression(): void
    {
        $operator = new NotInOperator();
        $platform = $this->createMock(AbstractPlatform::class);

        $expression = $operator->compile('e', 'e.status', ['draft', 'archived'], $platform);

        self::assertStringStartsWith('e.status NOT IN (:nin_', $expression->dql);
        self::assertCount(1, $expression->parameters);
        self::assertSame(['draft', 'archived'], array_values($expression->parameters)[0]);
    }

    public function testCompileRejectsEmptyValues(): void
    {
        $operator = new NotInOperator();
        $platform = $this->createMock(AbstractPlatform::class);

        $this->expectException(\InvalidArgumentException::class);

        $operator->compile('e', 'e.status', [], $platform);
    }
}
