<?php
declare(strict_types=1);

require_once __DIR__ . '/DetectionProviderInterface.php';
require_once __DIR__ . '/OpenAIProvenanceResponseMapper.php';

/**
 * Optional adapter for OpenAI Content Provenance checks.
 *
 * It is not wired into the upload handler. It sends an image externally only
 * when explicitly invoked and ENABLE_EXTERNAL_PROVENANCE is exactly "true".
 */
final class OpenAIProvenanceProvider implements DetectionProviderInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/content_provenance_checks';
    private const MAX_RESPONSE_BYTES = 262144;

    public function analyze(string $temporaryPath, string $mimeType): array
    {
        if (getenv('ENABLE_EXTERNAL_PROVENANCE') !== 'true') {
            return $this->result('unavailable', [], 'external_processing_disabled');
        }

        $apiKey = getenv('OPENAI_API_KEY');
        if (!is_string($apiKey) || trim($apiKey) === '') {
            return $this->result('unavailable', [], 'provider_not_configured');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true) ||
            !is_file($temporaryPath) || !is_readable($temporaryPath)) {
            return $this->result('failed', [], 'invalid_image_input');
        }

        if (!extension_loaded('curl') || !class_exists('CURLFile')) {
            return $this->result('unavailable', [], 'http_client_unavailable');
        }

        $fileSize = filesize($temporaryPath);
        if (!is_int($fileSize) || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
            return $this->result('failed', [], 'image_size_invalid');
        }

        $uploadName = match ($mimeType) {
            'image/jpeg' => 'upload.jpg',
            'image/png' => 'upload.png',
            'image/webp' => 'upload.webp',
        };

        $curl = curl_init(self::ENDPOINT);
        if ($curl === false) {
            return $this->result('unavailable', [], 'http_client_initialization_failed');
        }

        $responseBody = '';
        $responseTooLarge = false;
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ];

        try {
            $postFields = [
                'file' => new CURLFile($temporaryPath, $mimeType, $uploadName),
            ];

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody, &$responseTooLarge): int {
                    if (strlen($responseBody) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                        $responseTooLarge = true;
                        return 0;
                    }
                    $responseBody .= $chunk;
                    return strlen($chunk);
                },
            ]);

            $ok = curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($ok === false || $responseTooLarge) {
                return $this->result('unavailable', [], $responseTooLarge ? 'provider_response_too_large' : 'provider_transport_error');
            }

            if ($httpStatus === 404) {
                return $this->result('unavailable', [], 'provider_access_unavailable');
            }
            if ($httpStatus === 429) {
                return $this->result('unavailable', [], 'provider_rate_limited');
            }
            if ($httpStatus === 400 || $httpStatus === 413 || $httpStatus === 415) {
                return $this->result('failed', [], 'provider_rejected_image');
            }
            if ($httpStatus < 200 || $httpStatus >= 300) {
                return $this->result('unavailable', [], 'provider_request_failed');
            }

            try {
                $payload = json_decode($responseBody, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->result('failed', [], 'provider_invalid_json');
            }

            if (!is_array($payload)) {
                return $this->result('failed', [], 'provider_invalid_response');
            }

            return OpenAIProvenanceResponseMapper::map($payload);
        } finally {
            curl_close($curl);
            unset($postFields, $headers, $apiKey, $responseBody);
        }
    }

    /**
     * @param list<array{type: string, outcome: string, explanation: string}> $signals
     * @return array{
     *   provider: string,
     *   status: 'completed'|'inconclusive'|'unavailable'|'failed',
     *   signals: list<array{type: string, outcome: string, explanation: string}>,
     *   error_code: ?string
     * }
     */
    private function result(string $status, array $signals, ?string $errorCode): array
    {
        return [
            'provider' => 'openai_content_provenance',
            'status' => $status,
            'signals' => $signals,
            'error_code' => $errorCode,
        ];
    }
}
