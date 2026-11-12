<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\User;

use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function array_filter;
use function array_values;
use function is_int;
use function is_string;
use function method_exists;

/**
 * Builds the framework-free {@see UserRef} of a security user (since 1.13). The stable id follows the 1.12 rule of
 * {@see DefaultSentryUserContextProvider}: `getId()` returning a non-empty int, string or Stringable, else null (also
 * when `getId()` throws). Stateless.
 *
 * @internal
 */
final class UserRefFactory
{
    public static function fromUser(UserInterface $user): UserRef
    {
        return new UserRef($user->getUserIdentifier(), self::stableId($user), self::roles($user), $user);
    }

    /**
     * The role names; [] when `getRoles()` throws (the 1.12 provider never read them, so a failure must not drop the
     * user context).
     *
     * @return list<string>
     */
    private static function roles(UserInterface $user): array
    {
        try {
            $roles = $user->getRoles();
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter($roles, is_string(...)));
    }

    private static function stableId(UserInterface $user): ?string
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
