<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Security/RateLimiter.php';

function rateLimitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$directory = sys_get_temp_dir() . '/ai-image-detector-rate-test-' . bin2hex(random_bytes(8));
try {
    $first = RateLimiter::consume('client-a', $directory, 2, 600);
    $second = RateLimiter::consume('client-a', $directory, 2, 600);
    $blocked = RateLimiter::consume('client-a', $directory, 2, 600);
    $independent = RateLimiter::consume('client-b', $directory, 2, 600);

    rateLimitAssert($first === null, 'first request is allowed');
    rateLimitAssert($second === null, 'request within configured limit is allowed');
    rateLimitAssert(is_int($blocked) && $blocked > 0, 'request over the limit is blocked with a retry delay');
    rateLimitAssert($independent === null, 'separate caller has an independent quota');

    $stateFile = $directory . '/upload-rate-state.json';
    rateLimitAssert(is_file($stateFile), 'rate state is persisted');
    rateLimitAssert((fileperms($stateFile) & 0777) === 0600, 'rate state file has restrictive permissions');

    echo "Rate limiter tests passed.\n";
} finally {
    $stateFile = $directory . '/upload-rate-state.json';
    if (is_file($stateFile)) {
        unlink($stateFile);
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}
