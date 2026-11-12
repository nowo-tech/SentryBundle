<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

use function hash;
use function hash_hmac;

final class DefaultSentryUserContextProviderTest extends TestCase
{
    public function testUserWithIntIdSendsTheIdOnly(): void
    {
        $provider = new DefaultSentryUserContextProvider();

        self::assertSame(['id' => '42'], $provider->getUserContext(new IdUser(42, 'alice@example.com')));
    }

    public function testUserWithStringOrStringableId(): void
    {
        $provider = new DefaultSentryUserContextProvider();

        self::assertSame(['id' => 'u-7'], $provider->getUserContext(new IdUser('u-7', 'alice@example.com')));
        self::assertSame(['id' => '0190a1b2-uuid'], $provider->getUserContext(new IdUser(new StringId('0190a1b2-uuid'), 'alice@example.com')));
    }

    public function testUserWithoutUsableIdSendsAHashOfTheIdentifier(): void
    {
        $provider = new DefaultSentryUserContextProvider();
        $expected = ['id' => hash('sha256', 'alice@example.com')];

        self::assertSame($expected, $provider->getUserContext(new InMemoryUser('alice@example.com', null)));
        self::assertSame($expected, $provider->getUserContext(new IdUser(null, 'alice@example.com')));
        self::assertSame($expected, $provider->getUserContext(new IdUser('', 'alice@example.com')));
        self::assertSame($expected, $provider->getUserContext(new ThrowingIdUser('alice@example.com')));
    }

    public function testHashKeyMakesItAnHmac(): void
    {
        $provider = new DefaultSentryUserContextProvider(null, null, 'k3y');

        self::assertSame(['id' => hash_hmac('sha256', 'alice@example.com', 'k3y')], $provider->getUserContext(new InMemoryUser('alice@example.com', null)));
    }

    public function testDefaultHashIsAnHmacKeyedFromADerivedKernelSecretKey(): void
    {
        $derived  = hash_hmac('sha256', 'nowo_sentry.user_id.v1', 'kernel-s3cret', true);
        $provider = new DefaultSentryUserContextProvider(null, null, null, new ParameterContainer(['kernel.secret' => 'kernel-s3cret']));
        $context  = $provider->getUserContext(new InMemoryUser('admin', null));

        self::assertSame(['id' => hash_hmac('sha256', 'admin', $derived)], $context);
        // Not reversible from a plain hash, and the secret itself is never the HMAC key.
        self::assertNotSame(hash('sha256', 'admin'), $context['id'] ?? null);
        self::assertNotSame(hash_hmac('sha256', 'admin', 'kernel-s3cret'), $context['id'] ?? null);
        self::assertSame('nowo_sentry.user_id.v1', DefaultSentryUserContextProvider::KEY_DERIVATION_LABEL);
    }

    public function testExplicitHashKeyOverridesTheKernelSecret(): void
    {
        $provider = new DefaultSentryUserContextProvider(null, null, 'k3y', new ParameterContainer(['kernel.secret' => 'kernel-s3cret']));

        self::assertSame(['id' => hash_hmac('sha256', 'admin', 'k3y')], $provider->getUserContext(new InMemoryUser('admin', null)));
    }

    public function testMissingOrUnreadableKernelSecretFallsBackToPlainSha256(): void
    {
        $user = new InMemoryUser('admin', null);

        self::assertSame(['id' => hash('sha256', 'admin')], (new DefaultSentryUserContextProvider(null, null, null, new ParameterContainer([])))->getUserContext($user));
        self::assertSame(['id' => hash('sha256', 'admin')], (new DefaultSentryUserContextProvider(null, null, null, new ParameterContainer(['kernel.secret' => ''])))->getUserContext($user));
        self::assertSame(['id' => hash('sha256', 'admin')], (new DefaultSentryUserContextProvider(null, null, null, new ParameterContainer(['kernel.secret' => new RuntimeException('env not found')])))->getUserContext($user));
    }

    public function testDataCollectionUserInfoFalseWinsOverSendDefaultPii(): void
    {
        $user = new IdUser(42, 'alice@example.com');
        $hub  = new Hub(ClientBuilder::create(['send_default_pii' => true, 'data_collection' => ['user_info' => false]])->getClient());
        self::assertSame(['id' => '42'], (new DefaultSentryUserContextProvider($hub))->getUserContext($user));

        $hub = new Hub(ClientBuilder::create(['data_collection' => ['user_info' => true]])->getClient());
        self::assertSame(['id' => 'alice@example.com', 'username' => 'alice@example.com'], (new DefaultSentryUserContextProvider($hub))->getUserContext($user));
    }

    public function testEmptyIdentifierWithoutIdSendsNoUser(): void
    {
        self::assertNull((new DefaultSentryUserContextProvider())->getUserContext(new IdUser(null, '')));
        self::assertNull((new DefaultSentryUserContextProvider(null, true))->getUserContext(new IdUser(null, '')));
    }

    public function testExplicitOptInSendsTheIdentifierAsBefore(): void
    {
        $provider = new DefaultSentryUserContextProvider(null, true);

        self::assertSame(['id' => 'alice@example.com', 'username' => 'alice@example.com'], $provider->getUserContext(new IdUser(42, 'alice@example.com')));
    }

    public function testNullFollowsTheSentrySendDefaultPiiOption(): void
    {
        $user = new IdUser(42, 'alice@example.com');

        $pii = new Hub(ClientBuilder::create(['send_default_pii' => true])->getClient());
        self::assertSame(['id' => 'alice@example.com', 'username' => 'alice@example.com'], (new DefaultSentryUserContextProvider($pii))->getUserContext($user));

        $noPii = new Hub(ClientBuilder::create([])->getClient());
        self::assertSame(['id' => '42'], (new DefaultSentryUserContextProvider($noPii))->getUserContext($user));

        // Explicit false wins over send_default_pii: true.
        self::assertSame(['id' => '42'], (new DefaultSentryUserContextProvider($pii, false))->getUserContext($user));

        // No client on the hub, or a hub that throws: no identifier.
        self::assertSame(['id' => '42'], (new DefaultSentryUserContextProvider(new Hub()))->getUserContext($user));
        $broken = $this->createMock(HubInterface::class);
        $broken->method('getClient')->willThrowException(new RuntimeException('boom'));
        self::assertSame(['id' => '42'], (new DefaultSentryUserContextProvider($broken))->getUserContext($user));
    }
}
