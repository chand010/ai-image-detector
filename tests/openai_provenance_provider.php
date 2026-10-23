<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Detection/OpenAIProvenanceProvider.php';

function assertProviderValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) .
            "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

// This offline test must never send an image to an external service.
putenv('ENABLE_EXTERNAL_PROVENANCE=false');
putenv('OPENAI_API_KEY=');

$provider = new OpenAIProvenanceProvider();
$result = $provider->analyze('/does/not/exist.jpg', 'image/jpeg');

assertProviderValue('openai_content_provenance', $result['provider'], 'provider identity is stable');
assertProviderValue('unavailable', $result['status'], 'external processing is disabled by default');
assertProviderValue('external_processing_disabled', $result['error_code'], 'disabled state is explicit');
assertProviderValue([], $result['signals'], 'disabled provider returns no fabricated signals');

echo "OpenAI provenance provider offline tests passed.\n";
