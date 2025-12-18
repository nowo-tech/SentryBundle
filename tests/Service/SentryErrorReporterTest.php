<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Service;

use Nowo\SentryBundle\Service\SentryErrorReporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\EventId;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * Test case for SentryErrorReporter.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class SentryErrorReporterTest extends TestCase
{
    /**
     * Test that captureException successfully reports an exception.
     */
    public function testCaptureExceptionSuccess(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setExtra')
                    ->with('custom_message', 'Custom message');
                $callback($scope);

                return true;
            }));

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception, [], 'Custom message');

        $this->assertTrue($result);
    }

    /**
     * Test that captureException handles Sentry failures gracefully.
     */
    public function testCaptureExceptionHandlesSentryFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('captureException')
            ->willThrowException(new RuntimeException('Sentry error'));

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to capture exception in Sentry',
                $this->callback(function ($context) {
                    return isset($context['original_exception']) && isset($context['sentry_error']);
                })
            );

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception);

        $this->assertFalse($result);
    }

    /**
     * Test that captureException works without logger.
     */
    public function testCaptureExceptionWithoutLogger(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('captureException')
            ->willThrowException(new RuntimeException('Sentry error'));

        $reporter = new SentryErrorReporter($hub, null, ['enabled' => true]);
        $result = $reporter->captureException($exception);

        $this->assertFalse($result);
    }

    /**
     * Test that captureException adds context data.
     */
    public function testCaptureExceptionWithContext(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');
        $context = ['key1' => 'value1', 'key2' => 'value2'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) use ($context) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(2))
                    ->method('setExtra')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureException')
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception, $context);

        $this->assertTrue($result);
    }

    /**
     * Test that captureMessage successfully reports a message.
     */
    public function testCaptureMessageSuccess(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with('Test message', $this->isInstanceOf(Severity::class))
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Test message', 'error');

        $this->assertTrue($result);
    }

    /**
     * Test that captureMessage handles Sentry failures gracefully.
     */
    public function testCaptureMessageHandlesSentryFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('captureMessage')
            ->willThrowException(new RuntimeException('Sentry error'));

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to capture message in Sentry',
                $this->callback(function ($context) {
                    return isset($context['message']) && isset($context['level']);
                })
            );

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Test message', 'error');

        $this->assertFalse($result);
    }

    /**
     * Test that captureMessage adds context data.
     */
    public function testCaptureMessageWithContext(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $context = ['key1' => 'value1'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setExtra')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $hub->expects($this->once())
            ->method('captureMessage')
            ->willReturn(EventId::generate());

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Test message', 'error', $context);

        $this->assertTrue($result);
    }

    /**
     * Test that captureError is a convenience method for captureMessage.
     */
    public function testCaptureError(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('captureMessage')
            ->willReturn(EventId::generate());

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureError('Test error', ['context' => 'data']);

        $this->assertTrue($result);
    }

    /**
     * Test that addBreadcrumb successfully adds a breadcrumb.
     */
    public function testAddBreadcrumbSuccess(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('addBreadcrumb')
                    ->with($this->callback(function ($breadcrumb) {
                        return isset($breadcrumb['message']) 
                            && isset($breadcrumb['level'])
                            && isset($breadcrumb['data']);
                    }))
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->addBreadcrumb('Test breadcrumb', 'info', ['data' => 'value']);

        $this->assertTrue($result);
    }

    /**
     * Test that addBreadcrumb handles Sentry failures gracefully.
     */
    public function testAddBreadcrumbHandlesSentryFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Sentry error'));

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->addBreadcrumb('Test breadcrumb', 'info');

        $this->assertFalse($result);
    }

    /**
     * Test that setUser successfully sets user context.
     */
    public function testSetUserSuccess(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $userData = ['id' => '123', 'username' => 'testuser'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) use ($userData) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setUser')
                    ->with($userData);
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setUser($userData);

        $this->assertTrue($result);
    }

    /**
     * Test that setUser handles Sentry failures gracefully.
     */
    public function testSetUserHandlesSentryFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $userData = ['id' => '123'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Sentry error'));

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setUser($userData);

        $this->assertFalse($result);
    }

    /**
     * Test that setContext successfully sets context data.
     */
    public function testSetContextSuccess(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $context = ['key1' => 'value1', 'key2' => 'value2'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(2))
                    ->method('setExtra')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setContext($context);

        $this->assertTrue($result);
    }

    /**
     * Test that setContext handles Sentry failures gracefully.
     */
    public function testSetContextHandlesSentryFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $context = ['key1' => 'value1'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Sentry error'));

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setContext($context);

        $this->assertFalse($result);
    }

    /**
     * Test that mapLogLevelToSentryLevel maps levels correctly.
     */
    public function testMapLogLevelToSentryLevel(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);

        // Test all level mappings
        $hub->expects($this->exactly(6))
            ->method('captureMessage')
            ->willReturn(EventId::generate());

        $reporter->captureMessage('debug', 'debug');
        $reporter->captureMessage('info', 'info');
        $reporter->captureMessage('warning', 'warning');
        $reporter->captureMessage('warn', 'warn');
        $reporter->captureMessage('error', 'error');
        $reporter->captureMessage('fatal', 'fatal');
    }

    /**
     * Test that default level is error for unknown levels.
     */
    public function testMapLogLevelToSentryLevelDefault(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with('Test', $this->isInstanceOf(Severity::class))
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $reporter->captureMessage('Test', 'unknown-level');
    }

    /**
     * Test that captureException returns false when eventId is null.
     */
    public function testCaptureExceptionReturnsFalseWhenEventIdIsNull(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn(null);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception);

        $this->assertFalse($result);
    }

    /**
     * Test that captureException works without context and message.
     */
    public function testCaptureExceptionWithoutContextAndMessage(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->never())
            ->method('configureScope');

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception);

        $this->assertTrue($result);
    }

    /**
     * Test that captureException handles configureScope failure.
     */
    public function testCaptureExceptionHandlesConfigureScopeFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Scope error'));

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception, ['key' => 'value']);

        $this->assertFalse($result);
    }

    /**
     * Test that captureMessage returns false when eventId is null.
     */
    public function testCaptureMessageReturnsFalseWhenEventIdIsNull(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('captureMessage')
            ->willReturn(null);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Test message', 'error');

        $this->assertFalse($result);
    }

    /**
     * Test that captureMessage works without context.
     */
    public function testCaptureMessageWithoutContext(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->never())
            ->method('configureScope');

        $hub->expects($this->once())
            ->method('captureMessage')
            ->willReturn(EventId::generate());

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Test message', 'error');

        $this->assertTrue($result);
    }

    /**
     * Test that captureMessage handles configureScope failure.
     */
    public function testCaptureMessageHandlesConfigureScopeFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Scope error'));

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Test message', 'error', ['key' => 'value']);

        $this->assertFalse($result);
    }

    /**
     * Test that addBreadcrumb works without data.
     */
    public function testAddBreadcrumbWithoutData(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('addBreadcrumb')
                    ->with($this->callback(function ($breadcrumb) {
                        return isset($breadcrumb['message']) 
                            && isset($breadcrumb['level'])
                            && isset($breadcrumb['data']);
                    }))
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->addBreadcrumb('Test breadcrumb', 'info');

        $this->assertTrue($result);
    }

    /**
     * Test that addBreadcrumb handles configureScope failure.
     */
    public function testAddBreadcrumbHandlesConfigureScopeFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Scope error'));

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->addBreadcrumb('Test breadcrumb', 'info');

        $this->assertFalse($result);
    }

    /**
     * Test that setContext works with empty array.
     */
    public function testSetContextWithEmptyArray(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->never())
                    ->method('setExtra');
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setContext([]);

        $this->assertTrue($result);
    }

    /**
     * Test that setUser works with empty array.
     */
    public function testSetUserWithEmptyArray(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setUser')
                    ->with([]);
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setUser([]);

        $this->assertTrue($result);
    }

    /**
     * Test that setUser works without logger.
     */
    public function testSetUserWithoutLogger(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $userData = ['id' => '123'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Sentry error'));

        $reporter = new SentryErrorReporter($hub, null, ['enabled' => true]);
        $result = $reporter->setUser($userData);

        $this->assertFalse($result);
    }

    /**
     * Test that setContext works without logger.
     */
    public function testSetContextWithoutLogger(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $context = ['key1' => 'value1'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Sentry error'));

        $reporter = new SentryErrorReporter($hub, null, ['enabled' => true]);
        $result = $reporter->setContext($context);

        $this->assertFalse($result);
    }

    /**
     * Test that addBreadcrumb works without logger.
     */
    public function testAddBreadcrumbWithoutLogger(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Sentry error'));

        $reporter = new SentryErrorReporter($hub, null, ['enabled' => true]);
        $result = $reporter->addBreadcrumb('Test breadcrumb', 'info');

        $this->assertFalse($result);
    }

    /**
     * Test that logError handles logger failure gracefully.
     */
    public function testLogErrorHandlesLoggerFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('captureException')
            ->willThrowException(new RuntimeException('Sentry error'));

        $logger->expects($this->once())
            ->method('error')
            ->willThrowException(new RuntimeException('Logger error'));

        // Should not throw, even if logger fails
        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception);

        $this->assertFalse($result);
    }

    /**
     * Test that captureMessage handles configureScope failure with context.
     */
    public function testCaptureMessageHandlesConfigureScopeFailureWithContext(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Scope error'));

        $hub->expects($this->never())
            ->method('captureMessage');

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Test', 'error', ['key' => 'value']);

        $this->assertFalse($result);
    }

    /**
     * Test that captureMessage supports critical level.
     */
    public function testCaptureMessageWithCriticalLevel(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with('Critical message', $this->isInstanceOf(Severity::class))
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('Critical message', 'critical');

        $this->assertTrue($result);
    }

    /**
     * Test that captureException with only message (no context).
     */
    public function testCaptureExceptionWithOnlyMessage(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setExtra')
                    ->with('custom_message', 'Only message');
                $callback($scope);

                return true;
            }));

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureException')
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception, [], 'Only message');

        $this->assertTrue($result);
    }

    /**
     * Test that captureException with only context (no message).
     */
    public function testCaptureExceptionWithOnlyContext(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');
        $context = ['key' => 'value'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setExtra')
                    ->with('key', 'value');
                $callback($scope);

                return true;
            }));

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureException')
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception, $context);

        $this->assertTrue($result);
    }

    /**
     * Test that captureException with both context and message works correctly.
     */
    public function testCaptureExceptionWithContextAndMessage(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');
        $context = ['key1' => 'value1', 'key2' => 'value2'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(3))
                    ->method('setExtra')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureException')
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception, $context, 'Custom message');

        $this->assertTrue($result);
    }

    /**
     * Test that captureException works with different exception types.
     */
    public function testCaptureExceptionWithDifferentExceptionTypes(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $exceptions = [
            new \InvalidArgumentException('Invalid argument'),
            new \LogicException('Logic error'),
            new RuntimeException('Runtime error'),
            new \Exception('Generic exception'),
        ];

        foreach ($exceptions as $exception) {
            $hub = $this->createMock(HubInterface::class);
            $eventId = EventId::generate();
            $hub->expects($this->once())
                ->method('captureException')
                ->with($exception)
                ->willReturn($eventId);

            $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
            $result = $reporter->captureException($exception);

            $this->assertTrue($result);
        }
    }

    /**
     * Test that captureMessage works with all severity levels.
     */
    public function testCaptureMessageWithAllSeverityLevels(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $levels = ['debug', 'info', 'warning', 'warn', 'error', 'fatal', 'critical'];

        foreach ($levels as $level) {
            $hub = $this->createMock(HubInterface::class);
            $eventId = EventId::generate();
            $hub->expects($this->once())
                ->method('captureMessage')
                ->with('Test message', $this->isInstanceOf(Severity::class))
                ->willReturn($eventId);

            $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
            $result = $reporter->captureMessage('Test message', $level);

            $this->assertTrue($result);
        }
    }

    /**
     * Test that setContext handles different data types in context.
     */
    public function testSetContextWithDifferentDataTypes(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $context = [
            'string' => 'value',
            'integer' => 123,
            'float' => 45.67,
            'boolean' => true,
            'array' => ['nested' => 'data'],
            'null' => null,
        ];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(6))
                    ->method('setExtra')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setContext($context);

        $this->assertTrue($result);
    }

    /**
     * Test that constructor accepts null logger.
     */
    public function testConstructorWithNullLogger(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $reporter = new SentryErrorReporter($hub, null, ['enabled' => true]);

        $this->assertInstanceOf(SentryErrorReporter::class, $reporter);
    }

    /**
     * Test that constructor accepts logger.
     */
    public function testConstructorWithLogger(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);

        $this->assertInstanceOf(SentryErrorReporter::class, $reporter);
    }

    /**
     * Test that constructor accepts empty config.
     */
    public function testConstructorWithEmptyConfig(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $reporter = new SentryErrorReporter($hub, null, []);

        $this->assertInstanceOf(SentryErrorReporter::class, $reporter);
    }

    /**
     * Test that captureMessage with empty string message.
     */
    public function testCaptureMessageWithEmptyString(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with('', $this->isInstanceOf(Severity::class))
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureMessage('', 'error');

        $this->assertTrue($result);
    }

    /**
     * Test that addBreadcrumb with empty message.
     */
    public function testAddBreadcrumbWithEmptyMessage(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('addBreadcrumb')
                    ->with($this->callback(function ($breadcrumb) {
                        return isset($breadcrumb['message']) 
                            && $breadcrumb['message'] === ''
                            && isset($breadcrumb['level'])
                            && isset($breadcrumb['data']);
                    }))
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->addBreadcrumb('', 'info');

        $this->assertTrue($result);
    }

    /**
     * Test that captureException handles configureScope failure when adding context.
     */
    public function testCaptureExceptionHandlesConfigureScopeFailureWhenAddingContext(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $exception = new RuntimeException('Test exception');

        $hub->expects($this->once())
            ->method('configureScope')
            ->willThrowException(new RuntimeException('Scope error'));

        $hub->expects($this->never())
            ->method('captureException');

        $logger->expects($this->once())
            ->method('error');

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureException($exception, ['key' => 'value']);

        $this->assertFalse($result);
    }

    /**
     * Test that captureMessage handles mapLogLevelToSentryLevel failure.
     */
    public function testCaptureMessageHandlesMapLogLevelFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // This should not happen in practice, but we test the default case
        $eventId = EventId::generate();
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with('Test', $this->isInstanceOf(Severity::class))
            ->willReturn($eventId);

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        // Use an unknown level to trigger default case
        $result = $reporter->captureMessage('Test', 'unknown-level-xyz');

        $this->assertTrue($result);
    }

    /**
     * Test that addBreadcrumb handles mapLogLevelToSentryLevel failure.
     */
    public function testAddBreadcrumbHandlesMapLogLevelFailure(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('addBreadcrumb')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        // Use an unknown level to trigger default case
        $result = $reporter->addBreadcrumb('Test', 'unknown-level-xyz');

        $this->assertTrue($result);
    }

    /**
     * Test that setContext with numeric keys converts them to strings.
     */
    public function testSetContextWithNumericKeys(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $context = [
            0 => 'value0',
            1 => 'value1',
            'string_key' => 'value2',
        ];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(3))
                    ->method('setExtra')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->setContext($context);

        $this->assertTrue($result);
    }

    /**
     * Test that captureError passes context correctly.
     */
    public function testCaptureErrorPassesContextCorrectly(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $context = ['key1' => 'value1', 'key2' => 'value2'];

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function ($callback) {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(2))
                    ->method('setExtra')
                    ->willReturnSelf();
                $callback($scope);

                return true;
            }));

        $hub->expects($this->once())
            ->method('captureMessage')
            ->willReturn(EventId::generate());

        $reporter = new SentryErrorReporter($hub, $logger, ['enabled' => true]);
        $result = $reporter->captureError('Test error', $context, 'error');

        $this->assertTrue($result);
    }
}
