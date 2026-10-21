<?php
declare(strict_types=1);

final class LocalClassifierResponseMapper
{
    public static function map(array $payload): array
    {
        $probability = $payload['probability'] ?? null;
        $confidence = $payload['confidence'] ?? null;
        $score = $payload['ai_score'] ?? null;
        $verdict = $payload['verdict'] ?? null;
        $mode = $payload['mode'] ?? null;

        if (!self::isUnitInterval($probability) ||
            !self::isUnitInterval($confidence) ||
            !is_int($score) || $score < 1 || $score > 100 ||
            !is_string($verdict) || !in_array($verdict, ['ai', 'real', 'uncertain'], true) ||
            !is_string($mode) || $mode === '' || strlen($mode) > 32) {
            return self::failed('invalid_response');
        }

        $outcome = match ($verdict) {
            'ai' => 'ai_likely',
            'real' => 'low_ai_score',
            default => 'inconclusive',
        };

        return [
            'provider' => 'aidetect',
            'status' => 'completed',
            'signals' => [[
                'type' => 'ai_classifier',
                'outcome' => $outcome,
                'explanation' => sprintf(
                    'The local classifier estimated %.1f%% AI-generation probability (mode: %s; model label: %s). This probabilistic estimate is not proof of authorship.',
                    ((float) $probability) * 100,
                    $mode,
                    $verdict
                ),
            ]],
            'error_code' => null,
            'metrics' => [
                'probability' => round((float) $probability, 5),
                'confidence' => round((float) $confidence, 4),
                'ai_score' => $score,
                'verdict' => $verdict,
                'mode' => $mode,
            ],
        ];
    }

    public static function unavailable(string $errorCode): array
    {
        return [
            'provider' => 'aidetect',
            'status' => 'unavailable',
            'signals' => [[
                'type' => 'ai_classifier',
                'outcome' => 'inconclusive',
                'explanation' => 'The local classifier was unavailable. No conclusion about image authorship can be drawn.',
            ]],
            'error_code' => $errorCode,
            'metrics' => null,
        ];
    }

    private static function failed(string $errorCode): array
    {
        return [
            'provider' => 'aidetect',
            'status' => 'failed',
            'signals' => [[
                'type' => 'ai_classifier',
                'outcome' => 'inconclusive',
                'explanation' => 'The classifier response could not be validated. No conclusion about image authorship can be drawn.',
            ]],
            'error_code' => $errorCode,
            'metrics' => null,
        ];
    }

    private static function isUnitInterval(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) &&
            is_finite((float) $value) &&
            $value >= 0 && $value <= 1;
    }
}
