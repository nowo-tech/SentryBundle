<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Service;

use Psr\Log\LoggerInterface;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Throwable;

/**
 * Service for safely reporting errors to Sentry without breaking the application.
 *
 * This service provides a safe way to capture errors, exceptions, and messages
 * to Sentry. All operations are wrapped in try-catch blocks to ensure that
 * failures in Sentry reporting never break the application flow.
 *
 * Features:
 * - Safe error reporting (never throws exceptions)
 * - Support for exceptions, messages, and context data
 * - Automatic error logging if Sentry fails
 * - Configurable error levels
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final readonly class SentryErrorReporter
{
    /**
     * Constructs the Sentry error reporter service.
     *
     * @param HubInterface         $sentryHub The Sentry hub instance for reporting errors
     * @param LoggerInterface|null $logger    Optional logger for fallback error reporting
     * @param array<string, mixed> $config    Service configuration
     */
    public function __construct(
        private ?HubInterface $sentryHub,
        private ?LoggerInterface $logger = null,
        private array $config = []
    ) {
    }

    /**
     * Reports an exception to Sentry safely.
     *
     * This method captures an exception and sends it to Sentry. If the Sentry
     * operation fails, it logs the error but never throws an exception to avoid
     * breaking the application flow.
     *
     * @param Throwable            $exception The exception to report
     * @param array<string, mixed> $context   Additional context data to include
     * @param string|null          $message   Optional custom message to include
     *
     * @return bool True if the error was successfully reported, false otherwise
     */
    public function captureException(
        Throwable $exception,
        array $context = [],
        ?string $message = null
    ): bool {
        // Verify Sentry is available
        if ($this->sentryHub === null) {
            return false;
        }

        try {
            // Add context and message before capturing exception
            if (!empty($context) || $message !== null) {
                $this->sentryHub->configureScope(function ($scope) use ($context, $message): void {
                    if (!empty($context)) {
                        foreach ($context as $key => $value) {
                            $scope->setExtra((string) $key, $value);
                        }
                    }

                    if ($message !== null) {
                        $scope->setExtra('custom_message', $message);
                    }
                });
            }

            $eventId = $this->sentryHub->captureException($exception);

            return $eventId !== null;
        } catch (Throwable $e) {
            // Log the error but don't throw to avoid breaking the application
            $this->logError('Failed to capture exception in Sentry', [
                'original_exception' => $exception->getMessage(),
                'sentry_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reports a message to Sentry safely.
     *
     * This method sends a message to Sentry with an optional severity level.
     * If the Sentry operation fails, it logs the error but never throws an exception.
     *
     * @param string               $message The message to report
     * @param string               $level   The severity level (debug, info, warning, error, fatal)
     * @param array<string, mixed> $context Additional context data to include
     *
     * @return bool True if the message was successfully reported, false otherwise
     */
    public function captureMessage(
        string $message,
        string $level = 'error',
        array $context = []
    ): bool {
        // Verify Sentry is available
        if ($this->sentryHub === null) {
            return false;
        }

        try {
            $sentryLevel = $this->mapLogLevelToSentryLevel($level);

            // Add context before capturing message
            if (!empty($context)) {
                $this->sentryHub->configureScope(function ($scope) use ($context): void {
                    foreach ($context as $key => $value) {
                        $scope->setExtra((string) $key, $value);
                    }
                });
            }

            $eventId = $this->sentryHub->captureMessage($message, $sentryLevel);

            return $eventId !== null;
        } catch (Throwable $e) {
            // Log the error but don't throw to avoid breaking the application
            $this->logError('Failed to capture message in Sentry', [
                'message' => $message,
                'level' => $level,
                'sentry_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reports an error with context safely.
     *
     * This is a convenience method that combines message and context reporting.
     *
     * @param string               $message The error message
     * @param array<string, mixed> $context Additional context data
     * @param string               $level   The severity level
     *
     * @return bool True if the error was successfully reported, false otherwise
     */
    public function captureError(
        string $message,
        array $context = [],
        string $level = 'error'
    ): bool {
        return $this->captureMessage($message, $level, $context);
    }

    /**
     * Adds breadcrumb to Sentry for tracking user actions.
     *
     * Breadcrumbs are useful for tracking the sequence of events leading up to an error.
     *
     * @param string               $message The breadcrumb message
     * @param string               $level   The severity level
     * @param array<string, mixed> $data    Additional data for the breadcrumb
     *
     * @return bool True if the breadcrumb was successfully added, false otherwise
     */
    public function addBreadcrumb(
        string $message,
        string $level = 'info',
        array $data = []
    ): bool {
        // Verify Sentry is available
        if ($this->sentryHub === null) {
            return false;
        }

        try {
            $sentryLevel = $this->mapLogLevelToSentryLevel($level);

            // Use configureScope to add breadcrumb via scope
            $this->sentryHub->configureScope(function ($scope) use ($message, $sentryLevel, $data): void {
                $scope->addBreadcrumb([
                    'message' => $message,
                    'level' => $sentryLevel,
                    'data' => $data,
                ]);
            });

            return true;
        } catch (Throwable $e) {
            // Log the error but don't throw to avoid breaking the application
            $this->logError('Failed to add breadcrumb to Sentry', [
                'message' => $message,
                'level' => $level,
                'sentry_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sets user context for Sentry.
     *
     * This method sets user information that will be included in all subsequent
     * error reports until the scope is cleared or changed.
     *
     * @param array<string, mixed> $userData User data (id, username, email, etc.)
     *
     * @return bool True if the user context was successfully set, false otherwise
     */
    public function setUser(array $userData): bool
    {
        // Verify Sentry is available
        if ($this->sentryHub === null) {
            return false;
        }

        try {
            $this->sentryHub->configureScope(function ($scope) use ($userData): void {
                $scope->setUser($userData);
            });

            return true;
        } catch (Throwable $e) {
            $this->logError('Failed to set user context in Sentry', [
                'user_data' => $userData,
                'sentry_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sets additional context data for Sentry.
     *
     * This method sets extra data that will be included in all subsequent
     * error reports until the scope is cleared or changed.
     *
     * @param array<string, mixed> $context Context data
     *
     * @return bool True if the context was successfully set, false otherwise
     */
    public function setContext(array $context): bool
    {
        // Verify Sentry is available
        if ($this->sentryHub === null) {
            return false;
        }

        try {
            $this->sentryHub->configureScope(function ($scope) use ($context): void {
                foreach ($context as $key => $value) {
                    $scope->setExtra((string) $key, $value);
                }
            });

            return true;
        } catch (Throwable $e) {
            $this->logError('Failed to set context in Sentry', [
                'context' => $context,
                'sentry_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Maps log level string to Sentry severity level.
     *
     * @param string $level The log level (debug, info, warning, error, fatal)
     *
     * @return Severity The Sentry severity level
     */
    private function mapLogLevelToSentryLevel(string $level): Severity
    {
        return match (strtolower($level)) {
            'debug' => Severity::debug(),
            'info' => Severity::info(),
            'warning', 'warn' => Severity::warning(),
            'error' => Severity::error(),
            'fatal', 'critical' => Severity::fatal(),
            default => Severity::error(),
        };
    }

    /**
     * Logs an error using the logger if available.
     *
     * @param string               $message The error message
     * @param array<string, mixed> $context Additional context
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            try {
                $this->logger->error($message, $context);
            } catch (Throwable) {
                // If logging also fails, silently ignore to avoid breaking the application
            }
        }
    }
}
