<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\User;

use Nowo\SentryBundle\Internal\HiddenValues;
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
 * when `getId()` throws). A Stringable id whose `__toString()` throws gives a null id plus an internal flag
 * ({@see idFailed()}), never an exception. Stateless.
 *
 * @internal
 */
final class UserRefFactory
{
    /** Internal hidden value: `getId()` returned a Stringable whose `__toString()` threw (audit MX-S-1). */
    public const ID_FAILED = 'id_failed';

    public static function fromUser(UserInterface $user): UserRef
    {
        $idFailed = false;
        $ref      = new UserRef($user->getUserIdentifier(), self::stableId($user, $idFailed), self::roles($user), $user);
        if ($idFailed) {
            HiddenValues::set($ref, self::ID_FAILED, true);
        }

        return $ref;
    }

    /**
     * True when the user's `getId()` returned a Stringable that could not be converted. 1.12 let that exception escape
     * from the provider (the listener then sent no user unless the identifier was sent); the default provider keeps
     * that answer.
     */
    public static function idFailed(UserRef $user): bool
    {
        return HiddenValues::get($user, self::ID_FAILED) === true;
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

    /**
     * @param bool $failed set to true when a Stringable id cannot be converted
     */
    private static function stableId(UserInterface $user, bool &$failed): ?string
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
            try {
                $id = (string) $id;
            } catch (Throwable) {
                $failed = true;

                return null;
            }
        }

        return is_string($id) && $id !== '' ? $id : null;
    }
}
