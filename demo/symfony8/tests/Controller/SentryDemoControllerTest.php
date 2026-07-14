<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Sentry demo routes.
 */
final class SentryDemoControllerTest extends WebTestCase
{
    public function testSentryDemoIndexIsSuccessful(): void
    {
        $client = static::createClient(['environment' => 'test']);
        $client->request('GET', '/sentry');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Rutas de demo del Sentry Bundle');
        self::assertSelectorExists('a[href*="/sentry/sql-caught"]');
        self::assertSelectorExists('a[href*="/sentry/sql-uncaught"]');
    }

    public function testSqlCaughtRouteHandlesInvalidColumnGracefully(): void
    {
        $client = static::createClient(['environment' => 'test']);
        $client->request('GET', '/sentry/sql-caught');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.card-text', 'DBAL middleware reported this SQL error');
    }

    public function testSqlUncaughtRouteReturnsServerError(): void
    {
        $client = static::createClient(['environment' => 'test']);
        $client->request('GET', '/sentry/sql-uncaught');

        self::assertResponseStatusCodeSame(500);
    }
}
