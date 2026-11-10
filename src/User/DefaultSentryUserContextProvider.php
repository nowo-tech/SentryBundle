<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\User;

use SensitiveParameter;
use Sentry\State\HubInterface;
use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function hash;
use function hash_hmac;
use function is_int;
use function is_string;
use function method_exists;

/**
 * Default {@see SentryUserContextProviderInterface} (1.12+).
 *
 * - Identifier opt-in (`request_listener.send_user_identifier: true`, or `null` with the Sentry client option
 *   `send_default_pii: true`): `id` and `username` are `getUserIdentifier()`, the behaviour before 1.12.
 * - Otherwise only `id` is sent: the user's `getId()` when it returns a non-empty int, string or Stringable, else a
 *   SHA-256 hash of `getUserIdentifier()` (HMAC-SHA-256 when `request_listener.user_id_hash_key` is set). The
 *   identifier (often an e-mail) is never sent.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DefaultSentryUserContextProvider implements SentryUserContextProviderInterface
{
    /**
     * @param HubInterface|null $sentryHub Read for the client option `send_default_pii` when `$sendUserIdentifier` is null
     * @param bool|null $sendUserIdentifier true = send the identifier, false = never, null = follow `send_default_pii`
     * @param string|null $userIdHashKey HMAC key of the hashed id (null or '' = plain SHA-256)
     */
    public function __construct(
        private readonly ?HubInterface $sentryHub = null,
        private readonly ?bool $sendUserIdentifier = null,
        #[SensitiveParameter]
        private readonly ?string $userIdHashKey = null,
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

        return ['id' => $this->userIdHashKey !== null && $this->userIdHashKey !== ''
            ? hash_hmac('sha256', $identifier, $this->userIdHashKey)
            : hash('sha256', $identifier)];
    }

    private function shouldSendIdentifier(): bool
    {
        if ($this->sendUserIdentifier !== null) {
            return $this->sendUserIdentifier;
        }

        try {
            return $this->sentryHub?->getClient()?->getOptions()->shouldSendDefaultPii() ?? false;
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
