<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Detection/OpenAIProvenanceResponseMapper.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) .
            "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$detected = OpenAIProvenanceResponseMapper::map([
    'object' => 'content_provenance_check',
    'results' => [
        ['type' => 'c2pa', 'outcome' => 'detected', 'validation_state' => 'trusted'],
        ['type' => 'synthid', 'outcome' => 'not_detected', 'model' => null, 'generated_at' => null],
    ],
]);
assertSameValue('completed', $detected['status'], 'valid response completes');
assertSameValue(2, count($detected['signals']), 'both documented signals are retained');
assertSameValue('detected', $detected['signals'][0]['outcome'], 'C2PA outcome is mapped');
assertSameValue('not_detected', $detected['signals'][1]['outcome'], 'SynthID outcome is mapped');
assertSameValue(null, $detected['error_code'], 'valid response has no error code');

$empty = OpenAIProvenanceResponseMapper::map([
    'object' => 'content_provenance_check',
    'results' => [],
]);
assertSameValue('inconclusive', $empty['status'], 'empty signal list is inconclusive');

$invalid = OpenAIProvenanceResponseMapper::map(['object' => 'wrong', 'results' => []]);
assertSameValue('failed', $invalid['status'], 'wrong response object fails safely');
assertSameValue('invalid_response', $invalid['error_code'], 'invalid response has generic error code');

$malformedKnownSignal = OpenAIProvenanceResponseMapper::map([
    'object' => 'content_provenance_check',
    'results' => [
        ['type' => 'synthid', 'outcome' => 'maybe'],
    ],
]);
assertSameValue('failed', $malformedKnownSignal['status'], 'malformed known signal fails safely');
assertSameValue('invalid_response', $malformedKnownSignal['error_code'], 'malformed known signal exposes only a generic error');

$contradictoryC2pa = OpenAIProvenanceResponseMapper::map([
    'object' => 'content_provenance_check',
    'results' => [
        ['type' => 'c2pa', 'outcome' => 'detected', 'validation_state' => 'invalid'],
    ],
]);
assertSameValue('failed', $contradictoryC2pa['status'], 'detected outcome with invalid C2PA state is rejected');
assertSameValue([], $contradictoryC2pa['signals'], 'contradictory result exposes no provenance signal');

$unknownSignal = OpenAIProvenanceResponseMapper::map([
    'object' => 'content_provenance_check',
    'results' => [
        ['type' => 'future_signal', 'outcome' => 'detected'],
        ['type' => 'synthid', 'outcome' => 'not_detected'],
    ],
]);
assertSameValue('completed', $unknownSignal['status'], 'unknown future signal is ignored while known result is retained');
assertSameValue(1, count($unknownSignal['signals']), 'unknown signal is not exposed as evidence');

$invalidManifest = OpenAIProvenanceResponseMapper::map([
    'object' => 'content_provenance_check',
    'results' => [
        ['type' => 'c2pa', 'outcome' => 'not_detected', 'validation_state' => 'invalid'],
    ],
]);
assertSameValue('completed', $invalidManifest['status'], 'invalid manifest is still represented as a completed check');
assertSameValue(true, str_contains($invalidManifest['signals'][0]['explanation'], 'invalid'), 'invalid manifest is not described as trustworthy');

echo "Response mapper tests passed.\n";
