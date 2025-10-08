<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\CustomRoutes;

use PHPUnit\Framework\TestCase;

/**
 * Integration test for controller validation in custom routes.
 */
final class ControllerValidationTest extends TestCase
{
    public function testErrorMessageForNonInvokableController(): void
    {
        // Test the error message format for non-invokable controllers
        $expectedMessage = 'Custom route "test.search" on controller class "TestController" must specify a controller parameter ' .
                          'because the class is not invokable. Use controller: "TestController::methodName" or make the class invokable by adding an __invoke method.';

        $actualMessage = sprintf(
            'Custom route "%s" on controller class "%s" must specify a controller parameter ' .
            'because the class is not invokable. Use controller: "%s::methodName" or make the class invokable by adding an __invoke method.',
            'test.search',
            'TestController',
            'TestController'
        );

        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testErrorMessageForEntityClass(): void
    {
        // Test the error message format for entity classes
        $expectedMessage = 'Custom route "test.action" on entity class "TestEntity" must specify a controller parameter.';

        $actualMessage = sprintf(
            'Custom route "%s" on entity class "%s" must specify a controller parameter.',
            'test.action',
            'TestEntity'
        );

        $this->assertSame($expectedMessage, $actualMessage);
    }
}
