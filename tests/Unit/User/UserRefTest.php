<?php

declare(strict_types=1);

namespace Nowo\SentryBundle\Tests\Unit\User;

use Nowo\SentryBundle\Tests\Unit\Equivalence\RolesThrowingUser;
use Nowo\SentryBundle\User\UserRef;
use Nowo\SentryBundle\User\UserRefFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function json_encode;
use function ob_get_clean;
use function ob_start;
use function print_r;
use function serialize;
use function unserialize;
use function var_dump;
use function var_export;

final class UserRefTest extends TestCase
{
    public function testTheFactoryMapsTheUser(): void
    {
        $user = new IdUser(42, 'alice@example.com');
        $ref  = UserRefFactory::fromUser($user);

        self::assertSame('42', $ref->id);
        self::assertSame(['ROLE_USER'], $ref->roles);
        self::assertSame('alice@example.com', $ref->identifier());
        self::assertSame($user, $ref->user());

        self::assertNull(UserRefFactory::fromUser(new InMemoryUser('bob@example.com', null))->id, 'no getId()');
        self::assertNull(UserRefFactory::fromUser(new ThrowingIdUser('carol@example.com'))->id);
        self::assertSame('uuid-1', UserRefFactory::fromUser(new IdUser(new StringId('uuid-1'), 'x'))->id);
        self::assertSame([], UserRefFactory::fromUser(new RolesThrowingUser('dave@example.com'))->roles, 'roles that throw are dropped, the user stays');

        $failed = UserRefFactory::fromUser(new IdUser(new ThrowingStringId(), 'erin@example.com'));
        self::assertNull($failed->id, 'MX-S-1: no exception, no id');
        self::assertTrue(UserRefFactory::idFailed($failed));
        self::assertFalse(UserRefFactory::idFailed($ref));
        self::assertFalse(UserRefFactory::idFailed(clone $failed), 'a copy carries no hidden value');
    }

    public function testNoDumpExportCastOrSerializationShowsTheIdentifierOrTheUser(): void
    {
        $ref = new UserRef('alice@example.com', '42', [1 => 'ROLE_ADMIN'], new InMemoryUser('alice@example.com', 'hash-placeholder-5d1e'));
        self::assertSame(['ROLE_ADMIN'], $ref->roles, 'a list');

        ob_start();
        var_dump($ref);
        print_r($ref);
        $dumped = (string) ob_get_clean();
        $dumped .= (string) (new CliDumper())->dump((new VarCloner())->cloneVar($ref), true);
        $dumped .= var_export($ref, true) . json_encode($ref) . serialize($ref) . json_encode((array) $ref);

        self::assertStringNotContainsString('alice@example.com', $dumped);
        self::assertStringNotContainsString('hash-placeholder-5d1e', $dumped);
        self::assertStringContainsString('ROLE_ADMIN', $dumped, 'the public properties are shown');
    }

    public function testCopiesCarryNoIdentifierNorUser(): void
    {
        $ref = new UserRef('alice@example.com', '42', [], new InMemoryUser('alice@example.com', null));
        foreach ([clone $ref, unserialize(serialize($ref))] as $copy) {
            self::assertInstanceOf(UserRef::class, $copy);
            self::assertSame('42', $copy->id);
            self::assertSame('', $copy->identifier());
            self::assertNull($copy->user());
        }
        self::assertSame('alice@example.com', $ref->identifier());
        self::assertNull((new UserRef('x'))->user());
    }
}
