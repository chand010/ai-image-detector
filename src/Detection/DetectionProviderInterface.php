<?php
declare(strict_types=1);

/**
 * Contract for an image provenance or classification provider.
 *
 * Implementations must not store uploaded images, expose secrets, or convert
 * missing evidence into a claim that an image is human-created.
 */
interface DetectionProviderInterface
{
    /**
     * Analyze a validated temporary image without retaining it.
     *
     * @param string $temporaryPath Server-generated temporary upload path.
     * @param string $mimeType Server-detected MIME type.
     * @return array{
     *   provider: string,
     *   status: 'completed'|'inconclusive'|'unavailable'|'failed',
     *   signals: list<array{type: string, outcome: string, explanation: string}>,
     *   error_code: ?string,
 *   metrics: ?array{probability: float, confidence: float, ai_score: int, verdict: string, mode: string}
     * }
     */
    public function analyze(string $temporaryPath, string $mimeType): array;
}
