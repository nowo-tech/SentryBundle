<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\User;

use Nowo\SentryBundle\Internal\HiddenValues;

use function array_values;
use function is_object;
use function is_string;

/**
 * Framework-free reference to the authenticated user of a main request, given to a
 * {@see UserContextProviderInterface} (since 1.13, REQ-ARCH-001 rule 3: no Symfony `UserInterface` in a public port).
 *
 * - `id`: the user's stable id (`getId()` returning a non-empty int, string or Stringable), or null.
 * - `roles`: the user's role names.
 * - {@see identifier()}: `getUserIdentifier()` (often an e-mail) and {@see user()}: the host's user object. Neither is a
 *   property: they live in an internal {@see \WeakMap} keyed by this object, so a dump, `var_export()`, `(array)` cast,
 *   `json_encode()` or `serialize()` of the VO never shows them, and a `clone` or an unserialized copy has none.
 *
 * What reaches Sentry is only what the provider returns. Built per request and never stored (FrankenPHP worker mode,
 * kernel reset false).
 */
final readonly class UserRef
{
    /** Hidden value: the user identifier. */
    private const IDENTIFIER = 'identifier';

    /** Hidden value: the user object. */
    private const USER = 'user';

    /** @var list<string> */
    public array $roles;

    /**
     * @param string $identifier the user identifier (hidden, see the class comment)
     * @param string|null $id the user's stable id, if it has one
     * @param array<int, string> $roles the user's role names
     * @param object|null $user the host's user object (hidden, see the class comment)
     */
    public function __construct(
        string $identifier,
        public ?string $id = null,
        array $roles = [],
        ?object $user = null,
    ) {
        $this->roles = array_values($roles);
        HiddenValues::put($this, [self::IDENTIFIER => $identifier, self::USER => $user]);
    }

    /** The user identifier (often an e-mail); '' on a clone or an unserialized copy. */
    public function identifier(): string
    {
        $identifier = HiddenValues::get($this, self::IDENTIFIER);

        return is_string($identifier) ? $identifier : '';
    }

    /** The host's user object, or null (also on a clone or an unserialized copy). */
    public function user(): ?object
    {
        $user = HiddenValues::get($this, self::USER);

        return is_object($user) ? $user : null;
    }
}
