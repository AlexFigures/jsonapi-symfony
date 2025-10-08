<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Filter\Operator;

use JsonApi\Symfony\Filter\Operator\EqualOperator;
use JsonApi\Symfony\Filter\Operator\LikeOperator;
use JsonApi\Symfony\Filter\Operator\Registry;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testEmptyRegistry(): void
    {
        $registry = new Registry();
        
        $this->assertFalse($registry->has('eq'));
        $this->assertSame([], $registry->all());
    }

    public function testConstructorWithOperators(): void
    {
        $equalOperator = new EqualOperator();
        $likeOperator = new LikeOperator();
        
        $registry = new Registry([$equalOperator, $likeOperator]);
        
        $this->assertTrue($registry->has('eq'));
        $this->assertTrue($registry->has('like'));
        $this->assertSame($equalOperator, $registry->get('eq'));
        $this->assertSame($likeOperator, $registry->get('like'));
        $this->assertCount(2, $registry->all());
    }

    public function testRegisterOperator(): void
    {
        $registry = new Registry();
        $equalOperator = new EqualOperator();
        
        $registry->register($equalOperator);
        
        $this->assertTrue($registry->has('eq'));
        $this->assertSame($equalOperator, $registry->get('eq'));
    }

    public function testGetUnknownOperatorThrowsException(): void
    {
        $registry = new Registry();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operator "unknown".');
        
        $registry->get('unknown');
    }

    public function testConstructorWithIterableOperators(): void
    {
        // Test with an iterator (like tagged_iterator returns)
        $operators = new \ArrayIterator([
            new EqualOperator(),
            new LikeOperator(),
        ]);
        
        $registry = new Registry($operators);
        
        $this->assertTrue($registry->has('eq'));
        $this->assertTrue($registry->has('like'));
        $this->assertCount(2, $registry->all());
    }
}
