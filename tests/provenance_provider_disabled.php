<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Detection/OpenAIProvenanceProvider.php';

function expectValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

putenv('ENABLE_EXTERNAL_PROVENANCE=false');
putenv('OPENAI_API_KEY=');

$provider = new OpenAIProvenanceProvider();
$result = $provider->analyze('/not/a/real/file.png', 'image/png');

expectValue('unavailable', $result['status'], 'disabled provider must be unavailable');
expectValue('external_processing_disabled', $result['error_code'], 'disabled provider has a clear safe code');
expectValue([], $result['signals'], 'disabled provider returns no signals');

echo "External provider disabled-by-default test passed.\n";
