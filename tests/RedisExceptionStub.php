<?php

declare(strict_types=1);

namespace Redis\Exception;

use Exception;

/**
 * Stub for RedisException when phpredis extension is not installed.
 * Used by SentryRequestListener (catch) and SentryRequestListenerTest (throw).
 * Loaded via tests/bootstrap.php and composer autoload-dev "files".
 */
class RedisExceptionStub extends Exception
{
}
