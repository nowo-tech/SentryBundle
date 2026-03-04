<?php

declare(strict_types=1);

namespace Redis\Exception;

use Exception;

/**
 * Stub for RedisException when phpredis extension is not installed.
 * Used by SentryRequestListener (catch) and SentryRequestListenerTest (throw).
 * Loaded via composer autoload-dev "files" and phpstan.neon bootstrapFiles.
 */
class RedisExceptionStub extends Exception
{
}
