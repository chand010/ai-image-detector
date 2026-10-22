<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Detection/LocalClassifierProvider.php';

function expectProviderValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) .
            "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$provider = new LocalClassifierProvider();
putenv('ENABLE_LOCAL_CLASSIFIER=false');
$disabled = $provider->analyze('/not/a/real/file.png', 'image/png');
expectProviderValue('unavailable', $disabled['status'], 'disabled provider must not call detector');
expectProviderValue('local_classifier_disabled', $disabled['error_code'], 'disabled provider reports generic code');
expectProviderValue('inconclusive', $disabled['signals'][0]['outcome'], 'disabled is not a low AI score');

putenv('ENABLE_LOCAL_CLASSIFIER=true');
$invalidMime = $provider->analyze('/not/a/real/file.txt', 'text/plain');
expectProviderValue('failed', $invalidMime['status'], 'unsupported MIME is rejected before HTTP');
expectProviderValue('invalid_image_input', $invalidMime['error_code'], 'invalid input reports generic code');

$missingFile = $provider->analyze('/not/a/real/file.png', 'image/png');
expectProviderValue('failed', $missingFile['status'], 'missing temporary file is rejected before HTTP');
expectProviderValue('invalid_image_input', $missingFile['error_code'], 'missing file has generic error code');

putenv('ENABLE_LOCAL_CLASSIFIER');
echo "Local classifier provider guard tests passed.\n";
