<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\EventListener;

use Nowo\SentryBundle\Sentry\AccessDeniedExceptionHelper;
use Sentry\State\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Enriches Sentry when a sub-request access denied breaks the parent page.
 *
 * Pure access denied (main or sub) is ignored by {@see \Nowo\SentryBundle\Sentry\BeforeSendHandler}.
 * This listener only runs for the main request when the outer exception is not access denied
 * but the chain contains one (typical Twig "exception during template rendering" case).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SubRequestAccessDeniedContextListener
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ?HubInterface $sentryHub,
        private readonly RequestStack $requestStack,
        private readonly array $config
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!($this->config['enabled'] ?? true) || !$event->isMainRequest()) {
            return;
        }

        $hub = $this->sentryHub;
        if (!$hub instanceof HubInterface) {
            return;
        }

        $exception = $event->getThrowable();

        if (AccessDeniedExceptionHelper::isAccessDenied($exception)) {
            return;
        }

        if (!AccessDeniedExceptionHelper::hasAccessDeniedInChain($exception)) {
            return;
        }

        $this->enrichScope($event->getRequest(), $hub);
    }

    private function enrichScope(Request $request, HubInterface $hub): void
    {
        $parentRequest = $this->requestStack->getParentRequest();

        $hub->configureScope(static function ($scope) use ($request, $parentRequest): void {
            $scope->setTag('access_denied.origin', 'sub_request_broke_parent');
            $scope->setTag('access_denied.sub_request', 'true');
            $scope->setExtra('access_denied.route', $request->attributes->get('_route'));
            $scope->setExtra('access_denied.uri', $request->getRequestUri());
            $scope->setExtra('access_denied.controller', $request->attributes->get('_controller'));

            if ($parentRequest instanceof Request) {
                $scope->setExtra('access_denied.parent_route', $parentRequest->attributes->get('_route'));
                $scope->setExtra('access_denied.parent_uri', $parentRequest->getRequestUri());
            }
        });
    }
}
