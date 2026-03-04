<?php

declare(strict_types=1);

namespace Redis\Exception;

/**
 * Stub for RedisException when redis extension is not installed.
 * Used to cover the catch (RedisException|RuntimeException|Exception) branch in SentryRequestListener.
 */
class RedisException extends \Exception
{
}
