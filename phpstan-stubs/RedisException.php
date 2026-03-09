<?php

declare(strict_types=1);

namespace Redis\Exception;

use Exception;

/*
 * Stub for PHPStan: RedisException from phpredis extension (or tests/RedisExceptionStub.php at runtime).
 * Only defines the class if not already loaded (e.g. by Composer autoload files).
 */
if (!class_exists(RedisException::class, false)) {
    class RedisException extends Exception
    {
    }
}
