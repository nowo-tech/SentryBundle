<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Ensure RedisException stub is loaded before any test (CI may not load autoload-dev "files" in time)
if (!class_exists(Redis\Exception\RedisException::class, false)) {
    require_once __DIR__ . '/RedisExceptionStub.php';
}
