<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Stringable;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

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

final class IdUser implements UserInterface
{
    public function __construct(private readonly int|string|Stringable|null $id, private readonly string $identifier)
    {
    }

    public function getId(): int|string|Stringable|null
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}

final class ThrowingIdUser implements UserInterface
{
    public function __construct(private readonly string $identifier)
    {
    }

    public function getId(): int
    {
        throw new RuntimeException('not loaded');
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}

final class StringId implements Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
