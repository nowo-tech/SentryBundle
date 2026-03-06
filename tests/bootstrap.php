<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Ensure RedisException stub is loaded before any test (CI often uses --no-dev so autoload-dev "files" are not loaded)
if (!class_exists(Redis\Exception\RedisException::class, false)) {
    require_once __DIR__ . '/RedisExceptionStub.php';
}
