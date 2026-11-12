<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\Equivalence;

use Nowo\SentryBundle\EventListener\SentryRequestListener;
use Nowo\SentryBundle\Tests\Support\Legacy\DefaultSentryUserContextProviderV1120;
use Nowo\SentryBundle\Tests\Unit\User\IdUser;
use Nowo\SentryBundle\Tests\Unit\User\ParameterContainer;
use Nowo\SentryBundle\Tests\Unit\User\StringId;
use Nowo\SentryBundle\Tests\Unit\User\ThrowingIdUser;
use Nowo\SentryBundle\User\DefaultSentryUserContextProvider;
use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Nowo\SentryBundle\User\UserContextProviderInterface;
use Nowo\SentryBundle\User\UserRef;
use Nowo\SentryBundle\User\UserRefFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

use function count;
use function implode;
use function json_encode;
use function sprintf;

/**
 * 1.13 (REQ-ARCH-001 rule 3): the framework-free user context port sends exactly what the deprecated one sent, and both
 * send exactly what 1.12.0 sent (no PII beyond the old port).
 *
 * Provider matrix: user (int / string / Stringable / null / empty / throwing `getId()`, no `getId()`, empty identifier,
 * throwing `getRoles()`) × `send_user_identifier` (true / false / null) × hub (none, no client, `send_default_pii` on /
 * off, a hub that throws) × `user_id_hash_key` (null, '', a key) × parameters (none, `kernel.secret`, no secret, a
 * throwing secret). The verbatim 1.12.0 provider, the 1.13 provider through the deprecated port and through the new
 * port (built by {@see UserRefFactory}) must return the same array.
 *
 * Listener matrix: the request listener with a host provider on the deprecated port, the same logic on the new port, a
 * service on both ports (whose deprecated method returns another user), and the default provider; the scope user must
 * be the same for every user, anonymous included.
 */
final class UserContextEquivalenceMatrixTest extends TestCase
{
    #[Test]
    public function theDefaultProviderSendsTheSameThroughBothPortsAndAs1120(): void
    {
        $compared    = 0;
        $differences = [];
        $shapes      = [];
        foreach (self::hubs() as $hubName => $hub) {
            foreach ([true, false, null] as $send) {
                foreach ([null, '', 'k3y-for-tests'] as $key) {
                    foreach (self::parameters() as $parametersName => $parameters) {
                        $reference = new DefaultSentryUserContextProviderV1120($hub, $send, $key, $parameters);
                        $provider  = new DefaultSentryUserContextProvider($hub, $send, $key, $parameters);
                        foreach (self::users() as $userName => $user) {
                            $expected = $reference->getUserContext($user);
                            $answers  = ['deprecated port' => $provider->getUserContext($user), 'new port' => $provider->contextFor(UserRefFactory::fromUser($user))];
                            ++$compared;
                            $shapes[(string) json_encode($expected === null ? null : array_keys($expected))] = true;
                            foreach ($answers as $port => $answer) {
                                if ($answer !== $expected) {
                                    $differences[] = sprintf('%s send=%s key=%s %s %s: %s %s, 1.12.0 %s', $hubName, json_encode($send), json_encode($key), $parametersName, $userName, $port, json_encode($answer), json_encode($expected));
                                }
                            }
                        }
                    }
                }
            }
        }

        self::assertSame([], $differences, implode("\n", $differences));
        self::assertSame(count(self::hubs()) * 3 * 3 * count(self::parameters()) * count(self::users()), $compared);
        self::assertArrayHasKey('["id","username"]', $shapes, 'the opt-in identifier case occurs');
        self::assertArrayHasKey('["id"]', $shapes, 'the id / hash case occurs');
        self::assertArrayHasKey('null', $shapes, 'the no-user case occurs');
    }

    #[Test]
    public function theListenerSetsTheSameScopeUserThroughEveryPort(): void
    {
        $legacy = new class implements SentryUserContextProviderInterface {
            public function getUserContext(UserInterface $user): ?array
            {
                return $user->getUserIdentifier() === 'nobody' ? null : ['id' => 'opaque-' . $user->getUserIdentifier(), 'segment' => 'staff'];
            }
        };
        $new = new class implements UserContextProviderInterface {
            public function contextFor(UserRef $user): ?array
            {
                return $user->identifier() === 'nobody' ? null : ['id' => 'opaque-' . $user->identifier(), 'segment' => 'staff'];
            }
        };
        $dual = new class implements SentryUserContextProviderInterface, UserContextProviderInterface {
            public function contextFor(UserRef $user): ?array
            {
                return $user->identifier() === 'nobody' ? null : ['id' => 'opaque-' . $user->identifier(), 'segment' => 'staff'];
            }

            public function getUserContext(UserInterface $user): ?array
            {
                return ['id' => 'wrong-port'];
            }
        };
        $throwingNew = new class implements UserContextProviderInterface {
            public function contextFor(UserRef $user): ?array
            {
                throw new RuntimeException('provider down');
            }
        };
        $throwingLegacy = new class implements SentryUserContextProviderInterface {
            public function getUserContext(UserInterface $user): ?array
            {
                throw new RuntimeException('provider down');
            }
        };

        $users = [null, new InMemoryUser('alice', null), new InMemoryUser('nobody', null), new IdUser(7, 'bob@example.com'), new ThrowingIdUser('carol@example.com')];
        foreach ([[$legacy, $new], [$legacy, $dual], [$throwingLegacy, $throwingNew]] as [$reference, $candidate]) {
            for ($round = 0; $round < 3; ++$round) {
                foreach ($users as $user) {
                    self::assertSame($this->scopeUser($reference, $user), $this->scopeUser($candidate, $user));
                }
            }
        }

        $default = new DefaultSentryUserContextProvider(null, null, 'k3y-for-tests');
        $v1120   = new DefaultSentryUserContextProviderV1120(null, null, 'k3y-for-tests');
        foreach ($users as $user) {
            self::assertSame($this->scopeUser($v1120, $user), $this->scopeUser($default, $user), 'the default provider as in 1.12.0');
        }
        self::assertSame(['id' => 'opaque-alice', 'username' => null, 'segment' => 'staff', 'metadata' => []], $this->scopeUser($new, new InMemoryUser('alice', null)));
        self::assertNull($this->scopeUser($throwingNew, new InMemoryUser('alice', null)), 'a failing provider sends no user');
    }

    /**
     * Runs the listener for one request and returns the scope user it left (id, username, segment, metadata).
     *
     * @return array<string, mixed>|null
     */
    private function scopeUser(SentryUserContextProviderInterface|UserContextProviderInterface $provider, ?UserInterface $user): ?array
    {
        $hub          = new Hub(ClientBuilder::create([])->getClient());
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($user instanceof UserInterface ? new UsernamePasswordToken($user, 'main') : null);
        $security = new Security(new ServiceLocator(['security.token_storage' => static fn (): TokenStorage => $tokenStorage]));
        $listener = new SentryRequestListener($hub, ['set_user_info' => true, 'set_session_id' => false], 'prod', $security, $provider);
        $listener->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('https://example.com/'), HttpKernelInterface::MAIN_REQUEST));

        $seen = null;
        $hub->configureScope(static function (Scope $scope) use (&$seen): void {
            $scopeUser = $scope->getUser();
            $seen      = $scopeUser === null ? null : ['id' => $scopeUser->getId(), 'username' => $scopeUser->getUsername(), 'segment' => $scopeUser->getSegment(), 'metadata' => $scopeUser->getMetadata()];
        });

        return $seen;
    }

    /**
     * @return array<string, HubInterface|null>
     */
    private static function hubs(): array
    {
        $broken = new class extends Hub {
            public function getClient(): ?ClientInterface
            {
                throw new RuntimeException('boom');
            }
        };

        return [
            'no hub'         => null,
            'hub, no client' => new Hub(),
            'pii on'         => new Hub(ClientBuilder::create(['send_default_pii' => true])->getClient()),
            'pii off'        => new Hub(ClientBuilder::create(['send_default_pii' => false])->getClient()),
            'throwing hub'   => $broken,
        ];
    }

    /**
     * @return array<string, ParameterContainer|null>
     */
    private static function parameters(): array
    {
        return [
            'no parameters'   => null,
            'kernel.secret'   => new ParameterContainer(['kernel.secret' => 'kernel-secret-for-tests']),
            'empty secret'    => new ParameterContainer(['kernel.secret' => '']),
            'no secret'       => new ParameterContainer([]),
            'throwing secret' => new ParameterContainer(['kernel.secret' => new RuntimeException('env not set')]),
        ];
    }

    /**
     * @return array<string, UserInterface>
     */
    private static function users(): array
    {
        return [
            'int id'                 => new IdUser(42, 'alice@example.com'),
            'zero id'                => new IdUser(0, 'alice@example.com'),
            'string id'              => new IdUser('u-7', 'alice@example.com'),
            'Stringable id'          => new IdUser(new StringId('0190a1b2-uuid'), 'alice@example.com'),
            'empty Stringable id'    => new IdUser(new StringId(''), 'alice@example.com'),
            'null id'                => new IdUser(null, 'alice@example.com'),
            'empty id'               => new IdUser('', 'alice@example.com'),
            'throwing id'            => new ThrowingIdUser('alice@example.com'),
            'no getId()'             => new InMemoryUser('bob@example.com', null, ['ROLE_ADMIN']),
            'empty identifier'       => new IdUser(null, ''),
            'empty identifier, id 5' => new IdUser(5, ''),
            'throwing roles'         => new RolesThrowingUser('dave@example.com'),
        ];
    }
}
