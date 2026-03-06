<?php

declare(strict_types=1);

namespace Redis\Exception;

use Exception;

/*
 * Stub for RedisException when phpredis extension is not installed.
 * Used by SentryRequestListener (catch) and SentryRequestListenerTest (throw).
 * Loaded via composer autoload "files" so it is available in CI (including --no-dev).
 */
if (!class_exists(RedisException::class, false)) {
    class RedisExceptionStub extends Exception
    {
    }
}
