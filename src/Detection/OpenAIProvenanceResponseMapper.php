<?php
declare(strict_types=1);

/**
 * Converts the documented OpenAI Content Provenance response into the app's
 * provider-neutral result shape. This class performs no network requests.
 */
final class OpenAIProvenanceResponseMapper
{
    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   provider: string,
     *   status: 'completed'|'inconclusive'|'unavailable'|'failed',
     *   signals: list<array{type: string, outcome: string, explanation: string}>,
     *   error_code: ?string
     * }
     */
    public static function map(array $payload): array
    {
        $base = [
            'provider' => 'openai_content_provenance',
            'status' => 'failed',
            'signals' => [],
            'error_code' => 'invalid_response',
        ];

        if (($payload['object'] ?? null) !== 'content_provenance_check' ||
            !isset($payload['results']) || !is_array($payload['results'])) {
            return $base;
        }

        $signals = [];
        foreach ($payload['results'] as $result) {
            if (!is_array($result)) {
                return $base;
            }

            $type = $result['type'] ?? null;
            $outcome = $result['outcome'] ?? null;
            if (!is_string($type) || !in_array($type, ['c2pa', 'synthid'], true) ||
                !is_string($outcome) || !in_array($outcome, ['detected', 'not_detected'], true)) {
                // Ignore unknown future signal types, but reject malformed known types.
                if (is_string($type) && !in_array($type, ['c2pa', 'synthid'], true)) {
                    continue;
                }
                return $base;
            }

            if ($type === 'c2pa') {
                $validationState = $result['validation_state'] ?? null;
                if (!is_string($validationState) ||
                    !in_array($validationState, ['trusted', 'valid', 'invalid', 'not_present'], true)) {
                    return $base;
                }

                $explanation = match (true) {
                    $outcome === 'detected' && in_array($validationState, ['trusted', 'valid'], true)
                        => 'Supported OpenAI-issued AI-generation credentials were detected.',
                    $validationState === 'invalid'
                        => 'C2PA credentials were reported as invalid and should not be treated as reliable provenance evidence.',
                    $validationState === 'not_present'
                        => 'No C2PA manifest was available in this image.',
                    default
                        => 'No supported OpenAI-issued AI-generation credential was confirmed.',
                };
            } else {
                $explanation = $outcome === 'detected'
                    ? 'A supported SynthID watermark was detected.'
                    : 'No supported SynthID watermark was detected; this does not rule out AI generation.';
            }

            $signals[] = [
                'type' => $type,
                'outcome' => $outcome,
                'explanation' => $explanation,
            ];
        }

        if ($signals === []) {
            return [
                'provider' => 'openai_content_provenance',
                'status' => 'inconclusive',
                'signals' => [],
                'error_code' => null,
            ];
        }

        return [
            'provider' => 'openai_content_provenance',
            'status' => 'completed',
            'signals' => $signals,
            'error_code' => null,
        ];
    }
}
