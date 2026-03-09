<?php

declare(strict_types=1);

namespace Redis\Exception;

/**
 * Stub for PHPStan: RedisException from phpredis extension (or tests/RedisExceptionStub.php at runtime).
 * Only defines the class if not already loaded (e.g. by Composer autoload files).
 */
if (!class_exists(\Redis\Exception\RedisException::class, false)) {
    class RedisException extends \Exception
    {
    }
}
