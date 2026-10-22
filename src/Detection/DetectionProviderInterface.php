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
     * @return array<string, mixed> Normalized result. The metrics key is optional.
     */
    public function analyze(string $temporaryPath, string $mimeType): array;
}
