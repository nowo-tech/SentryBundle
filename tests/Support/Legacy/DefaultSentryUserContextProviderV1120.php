<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Support\Legacy;

use Nowo\SentryBundle\User\SentryUserContextProviderInterface;
use Psr\Container\ContainerInterface;
use SensitiveParameter;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\State\HubInterface;
use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function class_exists;
use function hash;
use function hash_hmac;
use function is_int;
use function is_string;
use function method_exists;

/**
 * Verbatim copy of the 1.12.0 `DefaultSentryUserContextProvider` (git tag v1.12.0; only the namespace, the class name
 * and this line changed): the reference of the 1.13 equivalence matrix. Never edit its logic.
 *
 * Default {@see SentryUserContextProviderInterface} (1.12+).
 *
 * - Identifier opt-in (`request_listener.send_user_identifier: true`, or `null` while the Sentry client collects user
 *   info: `data_collection.user_info: true` with sentry-php's data collection policy, else `send_default_pii: true`):
 *   `id` and `username` are `getUserIdentifier()`, the behaviour before 1.12.
 * - Otherwise only `id` is sent: the user's `getId()` when it returns a non-empty int, string or Stringable, else an
 *   HMAC-SHA-256 of `getUserIdentifier()`. The key is `request_listener.user_id_hash_key` when set, else a key derived
 *   from `kernel.secret` (`hash_hmac('sha256', 'nowo_sentry.user_id.v1', secret)`, domain-separated, so the secret
 *   itself never feeds a value that leaves the server); plain SHA-256 only when neither exists. The identifier (often
 *   an e-mail) is never sent.
 *
 * Worker mode: nothing is cached; the options, the policy and the key are read on every call.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DefaultSentryUserContextProviderV1120 implements SentryUserContextProviderInterface
{
    /** Domain separation label of the key derived from `kernel.secret`. */
    public const KEY_DERIVATION_LABEL = 'nowo_sentry.user_id.v1';

    /**
     * @param HubInterface|null $sentryHub Read for the client's user-info policy when `$sendUserIdentifier` is null
     * @param bool|null $sendUserIdentifier true = send the identifier, false = never, null = follow the Sentry client
     * @param string|null $userIdHashKey HMAC key of the hashed id (null or '' = key derived from `kernel.secret`)
     * @param ContainerInterface|null $parameters Parameter bag read for `kernel.secret` (lazily, per call)
     */
    public function __construct(
        private readonly ?HubInterface $sentryHub = null,
        private readonly ?bool $sendUserIdentifier = null,
        #[SensitiveParameter]
        private readonly ?string $userIdHashKey = null,
        private readonly ?ContainerInterface $parameters = null,
    ) {
    }

    public function getUserContext(UserInterface $user): ?array
    {
        $identifier = $user->getUserIdentifier();

        if ($this->shouldSendIdentifier()) {
            return $identifier !== '' ? ['id' => $identifier, 'username' => $identifier] : null;
        }

        $id = $this->stableId($user);
        if ($id !== null) {
            return ['id' => $id];
        }

        if ($identifier === '') {
            return null;
        }

        $key = $this->hashKey();

        return ['id' => $key !== null ? hash_hmac('sha256', $identifier, $key) : hash('sha256', $identifier)];
    }

    private function hashKey(): ?string
    {
        if ($this->userIdHashKey !== null && $this->userIdHashKey !== '') {
            return $this->userIdHashKey;
        }

        $secret = null;
        if ($this->parameters instanceof ContainerInterface) {
            try {
                $secret = $this->parameters->has('kernel.secret') ? $this->parameters->get('kernel.secret') : null;
            } catch (Throwable) {
                $secret = null;
            }
        }

        return is_string($secret) && $secret !== '' ? hash_hmac('sha256', self::KEY_DERIVATION_LABEL, $secret, true) : null;
    }

    private function shouldSendIdentifier(): bool
    {
        if ($this->sendUserIdentifier !== null) {
            return $this->sendUserIdentifier;
        }

        if (!$this->sentryHub instanceof HubInterface) {
            return false;
        }

        try {
            // sentry/sentry 4.34+: `data_collection.user_info` wins over the legacy `send_default_pii`.
            if (class_exists(DataCollectionPolicy::class)) {
                return DataCollectionPolicy::fromHub($this->sentryHub)->shouldCollectUserInfo();
            }

            return $this->sentryHub->getClient()?->getOptions()->shouldSendDefaultPii() ?? false;
        } catch (Throwable) {
            return false;
        }
    }

    private function stableId(UserInterface $user): ?string
    {
        if (!method_exists($user, 'getId')) {
            return null;
        }

        try {
            $id = $user->getId();
        } catch (Throwable) {
            return null;
        }

        if (is_int($id)) {
            return (string) $id;
        }
        if ($id instanceof Stringable) {
            $id = (string) $id;
        }

        return is_string($id) && $id !== '' ? $id : null;
    }
}
