<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Detection/LocalClassifierResponseMapper.php';

function assertSameMapperValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) .
            "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$valid = LocalClassifierResponseMapper::map([
    'probability' => 0.82,
    'confidence' => 0.64,
    'ai_score' => 82,
    'verdict' => 'ai',
    'mode' => 'calibrated',
]);
assertSameMapperValue('completed', $valid['status'], 'valid response completes');
assertSameMapperValue('ai_likely', $valid['signals'][0]['outcome'], 'AI label maps to a cautious outcome');
assertSameMapperValue(0.82, $valid['metrics']['probability'], 'probability is retained');
assertSameMapperValue(null, $valid['error_code'], 'valid response has no error');

$lowScore = LocalClassifierResponseMapper::map([
    'probability' => 0.12,
    'confidence' => 0.76,
    'ai_score' => 12,
    'verdict' => 'real',
    'mode' => 'calibrated',
]);
assertSameMapperValue('low_ai_score', $lowScore['signals'][0]['outcome'], 'real label is not proof of human authorship');

$uncertain = LocalClassifierResponseMapper::map([
    'probability' => 0.5,
    'confidence' => 0.0,
    'ai_score' => 50,
    'verdict' => 'uncertain',
    'mode' => 'default',
]);
assertSameMapperValue('inconclusive', $uncertain['signals'][0]['outcome'], 'uncertain label remains inconclusive');

$invalid = LocalClassifierResponseMapper::map([
    'probability' => 1.4,
    'confidence' => 0.1,
    'ai_score' => 140,
    'verdict' => 'ai',
    'mode' => 'calibrated',
]);
assertSameMapperValue('failed', $invalid['status'], 'out-of-range scores fail closed');
assertSameMapperValue('invalid_response', $invalid['error_code'], 'invalid response gets a generic error code');

$disabled = LocalClassifierResponseMapper::unavailable('detector_disabled');
assertSameMapperValue('unavailable', $disabled['status'], 'disabled detector is not a negative result');
assertSameMapperValue('inconclusive', $disabled['signals'][0]['outcome'], 'unavailable result is inconclusive');

echo "Local classifier response mapper tests passed.\n";
