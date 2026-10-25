<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Detection/HuggingFaceImageClassifier.php';

function expectClassifierValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

putenv('ENABLE_EXTERNAL_CLASSIFIER=false');
putenv('HF_TOKEN=');

$classifier = new HuggingFaceImageClassifier();
$result = $classifier->analyze('/not/a/real/file.png', 'image/png');

expectClassifierValue('unavailable', $result['status'], 'disabled classifier must be unavailable');
expectClassifierValue('external_classifier_disabled', $result['error_code'], 'disabled classifier has a safe status code');
expectClassifierValue([], $result['signals'], 'disabled classifier returns no signals');
expectClassifierValue(null, $result['label'], 'disabled classifier returns no fabricated label');

echo "Classifier disabled-by-default test passed.\n";
