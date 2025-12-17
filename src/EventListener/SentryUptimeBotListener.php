<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Listener that handles requests from uptime monitoring bots
 *
 * This listener intercepts health check requests from various monitoring services
 * (Sentry Uptime Bot, Uptime-Kuma, and kube-probe) and returns a simple OK response
 * for specific paths. This prevents these monitoring requests from being processed
 * by the full application stack.
 *
 * The listener runs with priority 255 to ensure it executes early in the request lifecycle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final readonly class SentryUptimeBotListener
{
    /**
     * Constructs the Sentry uptime bot listener
     *
     * @param array<string, mixed> $config The listener configuration
     */
    public function __construct(private array $config)
    {
    }

    /**
     * Handles requests from uptime monitoring bots
     *
     * This method performs the following checks:
     * 1. Identifies if the request is from a known monitoring bot (configurable)
     * 2. Verifies if the request is for monitored paths (configurable)
     * 3. Returns a 200 OK response immediately if both conditions are met
     *
     * This prevents monitoring requests from triggering full application logic,
     * authentication, or logging systems.
     *
     * @param RequestEvent $event The kernel request event containing request information
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Check if listener is enabled
        if (!($this->config['enabled'] ?? true)) {
            return;
        }

        $request = $event->getRequest();
        $userAgent = $request->headers->get('User-Agent');
        $pathInfo = $request->getPathInfo();

        $userAgents = $this->config['user_agents'] ?? ['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe'];
        $paths = $this->config['paths'] ?? ['/dashboard', '/', '/login'];

        $isBot = false;
        if ($userAgent) {
            foreach ($userAgents as $botUserAgent) {
                if (str_starts_with($userAgent, $botUserAgent)) {
                    $isBot = true;
                    break;
                }
            }
        }

        $isMonitoredPath = false;
        foreach ($paths as $path) {
            if ($pathInfo === $path) {
                $isMonitoredPath = true;
                break;
            }
            if ($path !== '/' && str_starts_with($pathInfo, $path)) {
                $isMonitoredPath = true;
                break;
            }
        }

        if ($isBot && $isMonitoredPath) {
            $response = new Response('OK', Response::HTTP_OK);
            $event->setResponse($response);
        }
    }
}
