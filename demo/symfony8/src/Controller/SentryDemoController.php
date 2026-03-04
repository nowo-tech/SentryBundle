<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\SentryBundle\Service\SentryErrorReporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Demo controller to test all Sentry Bundle use cases (SentryErrorReporter, listeners).
 */
#[Route(path: '/sentry', name: 'sentry_demo_')]
class SentryDemoController extends AbstractController
{
    #[Route(path: '', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('sentry_demo/index.html.twig', [
            'routes' => [
                'capture_exception' => $this->generateUrl('sentry_demo_capture_exception'),
                'capture_message' => $this->generateUrl('sentry_demo_capture_message'),
                'capture_error' => $this->generateUrl('sentry_demo_capture_error'),
                'add_breadcrumb' => $this->generateUrl('sentry_demo_add_breadcrumb'),
                'set_user' => $this->generateUrl('sentry_demo_set_user'),
                'set_context' => $this->generateUrl('sentry_demo_set_context'),
                'complete_example' => $this->generateUrl('sentry_demo_complete_example'),
                'safe_operation' => $this->generateUrl('sentry_demo_safe_operation'),
                'access_denied' => $this->generateUrl('sentry_demo_access_denied'),
                'trigger_error' => $this->generateUrl('sentry_demo_trigger_error'),
            ],
        ]);
    }

    #[Route(path: '/capture-exception', name: 'capture_exception', methods: ['GET'])]
    public function captureException(SentryErrorReporter $errorReporter): Response
    {
        $exception = new \RuntimeException('Demo exception for Sentry (captureException)');
        $errorReporter->captureException(
            $exception,
            ['demo' => true, 'route' => 'sentry/capture-exception'],
            'Custom message: exception captured safely'
        );

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'captureException',
            'title' => 'captureException',
            'message' => 'Exception was captured and sent to Sentry (if DSN is set). Application continued normally.',
        ]);
    }

    #[Route(path: '/capture-message', name: 'capture_message', methods: ['GET'])]
    public function captureMessage(Request $request, SentryErrorReporter $errorReporter): Response
    {
        $level = $request->query->getString('level', 'info');
        $errorReporter->captureMessage(
            'Demo message from Sentry Bundle (captureMessage)',
            $level,
            ['demo' => true, 'level' => $level]
        );

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'captureMessage',
            'title' => 'captureMessage',
            'message' => sprintf('Message sent to Sentry with level "%s".', $level),
        ]);
    }

    #[Route(path: '/capture-error', name: 'capture_error', methods: ['GET'])]
    public function captureError(SentryErrorReporter $errorReporter): Response
    {
        $errorReporter->captureError('Demo error (captureError)', ['source' => 'sentry_demo'], 'warning');

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'captureError',
            'title' => 'captureError',
            'message' => 'Error captured via captureError (convenience method).',
        ]);
    }

    #[Route(path: '/add-breadcrumb', name: 'add_breadcrumb', methods: ['GET'])]
    public function addBreadcrumb(SentryErrorReporter $errorReporter): Response
    {
        $errorReporter->addBreadcrumb('User opened Sentry demo breadcrumb page', 'info', ['page' => 'add-breadcrumb']);
        $errorReporter->addBreadcrumb('Second breadcrumb', 'debug', ['step' => 2]);

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'addBreadcrumb',
            'title' => 'addBreadcrumb',
            'message' => 'Breadcrumbs were added. They will appear in the next Sentry event.',
        ]);
    }

    #[Route(path: '/set-user', name: 'set_user', methods: ['GET'])]
    public function setUser(SentryErrorReporter $errorReporter): Response
    {
        $errorReporter->setUser([
            'id' => 'demo-user-123',
            'email' => 'demo@example.com',
            'username' => 'sentry_demo_user',
        ]);

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'setUser',
            'title' => 'setUser',
            'message' => 'User context set. Next Sentry events will include this user.',
        ]);
    }

    #[Route(path: '/set-context', name: 'set_context', methods: ['GET'])]
    public function setContext(SentryErrorReporter $errorReporter): Response
    {
        $errorReporter->setContext([
            'feature' => 'sentry_demo',
            'action' => 'set_context',
            'timestamp' => date('c'),
        ]);

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'setContext',
            'title' => 'setContext',
            'message' => 'Extra context set. It will be attached to the next Sentry event.',
        ]);
    }

    #[Route(path: '/complete-example', name: 'complete_example', methods: ['GET'])]
    public function completeExample(SentryErrorReporter $errorReporter): Response
    {
        $errorReporter->setUser(['id' => '1', 'username' => 'demo']);
        $errorReporter->addBreadcrumb('Started complete example', 'info');
        $errorReporter->addBreadcrumb('About to capture message', 'debug', ['step' => 2]);
        $errorReporter->captureMessage('Complete example message', 'info', ['example' => true]);
        $errorReporter->addBreadcrumb('Complete example finished', 'info');

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'completeExample',
            'title' => 'completeExample',
            'message' => 'User, breadcrumbs and message were sent. Check Sentry for the full context.',
        ]);
    }

    #[Route(path: '/safe-operation', name: 'safe_operation', methods: ['GET'])]
    public function safeOperation(SentryErrorReporter $errorReporter): Response
    {
        $errorReporter->captureException(new \RuntimeException('This should never break the app'));
        $errorReporter->captureMessage('Safe message', 'info');

        return $this->render('sentry_demo/result.html.twig', [
            'use_case' => 'safeOperation',
            'title' => 'safeOperation',
            'message' => 'SentryErrorReporter never throws. Application always continues.',
        ]);
    }

    #[Route(path: '/access-denied', name: 'access_denied', methods: ['GET'])]
    public function accessDenied(): never
    {
        throw new AccessDeniedException('Demo: this AccessDeniedException should NOT be reported to Sentry (by design).');
    }

    #[Route(path: '/trigger-error', name: 'trigger_error', methods: ['GET'])]
    public function triggerError(): never
    {
        throw new \RuntimeException('Demo uncaught exception: Sentry SDK will capture this automatically.');
    }
}
