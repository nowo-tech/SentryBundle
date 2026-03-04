<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Service;

use Psr\Log\LoggerInterface;
use Sentry\Breadcrumb;
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
     * @param HubInterface $sentryHub The Sentry hub instance for reporting errors
     * @param LoggerInterface|null $logger Optional logger for fallback error reporting
     */
    public function __construct(
        private ?HubInterface $sentryHub,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Reports an exception to Sentry safely.
     *
     * This method captures an exception and sends it to Sentry. If the Sentry
     * operation fails, it logs the error but never throws an exception to avoid
     * breaking the application flow.
     *
     * @param Throwable $exception The exception to report
     * @param array<string, mixed> $context Additional context data to include
     * @param string|null $message Optional custom message to include
     *
     * @return bool True if the error was successfully reported, false otherwise
     */
    public function captureException(
        Throwable $exception,
        array $context = [],
        ?string $message = null
    ): bool {
        // Verify Sentry is available
        if (!$this->sentryHub instanceof HubInterface) {
            return false;
        }

        try {
            // Add context and message before capturing exception
            if ($context !== [] || $message !== null) {
                $this->sentryHub->configureScope(static function ($scope) use ($context, $message): void {
                    foreach ($context as $key => $value) {
                        $scope->setExtra((string) $key, $value);
                    }

                    if ($message !== null) {
                        $scope->setExtra('custom_message', $message);
                    }
                });
            }

            $eventId = $this->sentryHub->captureException($exception);

            return $eventId instanceof \Sentry\EventId;
        } catch (Throwable $e) {
            // Log the error but don't throw to avoid breaking the application
            $this->logError('Failed to capture exception in Sentry', [
                'original_exception' => $exception->getMessage(),
                'sentry_error'       => $e->getMessage(),
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
     * @param string $message The message to report
     * @param string $level The severity level (debug, info, warning, error, fatal)
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
        if (!$this->sentryHub instanceof HubInterface) {
            return false;
        }

        try {
            $sentryLevel = $this->mapLogLevelToSentryLevel($level);

            // Add context before capturing message
            if ($context !== []) {
                $this->sentryHub->configureScope(static function ($scope) use ($context): void {
                    foreach ($context as $key => $value) {
                        $scope->setExtra((string) $key, $value);
                    }
                });
            }

            $eventId = $this->sentryHub->captureMessage($message, $sentryLevel);

            return $eventId instanceof \Sentry\EventId;
        } catch (Throwable $e) {
            // Log the error but don't throw to avoid breaking the application
            $this->logError('Failed to capture message in Sentry', [
                'message'      => $message,
                'level'        => $level,
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
     * @param string $message The error message
     * @param array<string, mixed> $context Additional context data
     * @param string $level The severity level
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
     * @param string $message The breadcrumb message
     * @param string $level The severity level
     * @param array<string, mixed> $data Additional data for the breadcrumb
     *
     * @return bool True if the breadcrumb was successfully added, false otherwise
     */
    public function addBreadcrumb(
        string $message,
        string $level = 'info',
        array $data = []
    ): bool {
        // Verify Sentry is available
        if (!$this->sentryHub instanceof HubInterface) {
            return false;
        }

        try {
            $breadcrumbLevel = $this->mapLogLevelToBreadcrumbLevel($level);
            $breadcrumb      = new Breadcrumb(
                $breadcrumbLevel,
                Breadcrumb::TYPE_DEFAULT,
                'app',
                $message,
                $data,
            );

            $this->sentryHub->configureScope(static function ($scope) use ($breadcrumb): void {
                $scope->addBreadcrumb($breadcrumb);
            });

            return true;
        } catch (Throwable $e) {
            // Log the error but don't throw to avoid breaking the application
            $this->logError('Failed to add breadcrumb to Sentry', [
                'message'      => $message,
                'level'        => $level,
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
        if (!$this->sentryHub instanceof HubInterface) {
            return false;
        }

        try {
            $this->sentryHub->configureScope(static function ($scope) use ($userData): void {
                $scope->setUser($userData);
            });

            return true;
        } catch (Throwable $e) {
            $this->logError('Failed to set user context in Sentry', [
                'user_data'    => $userData,
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
     * @param array<int|string, mixed> $context Context data (keys cast to string for Sentry)
     *
     * @return bool True if the context was successfully set, false otherwise
     */
    public function setContext(array $context): bool
    {
        // Verify Sentry is available
        if (!$this->sentryHub instanceof HubInterface) {
            return false;
        }

        try {
            $this->sentryHub->configureScope(static function ($scope) use ($context): void {
                foreach ($context as $key => $value) {
                    $scope->setExtra((string) $key, $value);
                }
            });

            return true;
        } catch (Throwable $e) {
            $this->logError('Failed to set context in Sentry', [
                'context'      => $context,
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
            'info'  => Severity::info(),
            'warning', 'warn' => Severity::warning(),
            'error' => Severity::error(),
            'fatal', 'critical' => Severity::fatal(),
            default => Severity::error(),
        };
    }

    /**
     * Maps log level string to Breadcrumb level constant.
     *
     * @param string $level The log level (debug, info, warning, error, fatal)
     *
     * @return string One of Breadcrumb::LEVEL_* constants
     */
    private function mapLogLevelToBreadcrumbLevel(string $level): string
    {
        return match (strtolower($level)) {
            'debug' => Breadcrumb::LEVEL_DEBUG,
            'info'  => Breadcrumb::LEVEL_INFO,
            'warning', 'warn' => Breadcrumb::LEVEL_WARNING,
            'error' => Breadcrumb::LEVEL_ERROR,
            'fatal', 'critical' => Breadcrumb::LEVEL_FATAL,
            default => Breadcrumb::LEVEL_ERROR,
        };
    }

    /**
     * Logs an error using the logger if available.
     *
     * @param string $message The error message
     * @param array<string, mixed> $context Additional context
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger instanceof LoggerInterface) {
            try {
                $this->logger->error($message, $context);
            } catch (Throwable) {
                // If logging also fails, silently ignore to avoid breaking the application
            }
        }
    }
}
