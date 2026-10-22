<?php
declare(strict_types=1);

require_once __DIR__ . '/DetectionProviderInterface.php';
require_once __DIR__ . '/LocalClassifierResponseMapper.php';

/**
 * Optional adapter for the local aidetect HTTP service.
 *
 * The destination is fixed to the internal Docker-network service name. Do not
 * make it user-configurable: arbitrary URLs could turn this into an SSRF proxy.
 * The provider is disabled unless ENABLE_LOCAL_CLASSIFIER is exactly "true".
 */
final class LocalClassifierProvider implements DetectionProviderInterface
{
    private const ENDPOINT = 'http://aidetect:8000/analyze';
    private const MAX_RESPONSE_BYTES = 65536;
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function analyze(string $temporaryPath, string $mimeType): array
    {
        if (getenv('ENABLE_LOCAL_CLASSIFIER') !== 'true') {
            return LocalClassifierResponseMapper::unavailable('local_classifier_disabled');
        }

        $allowedMimes = [
            'image/jpeg' => 'upload.jpg',
            'image/png' => 'upload.png',
            'image/webp' => 'upload.webp',
        ];
        if (!isset($allowedMimes[$mimeType]) ||
            !is_file($temporaryPath) || !is_readable($temporaryPath)) {
            return $this->failed('invalid_image_input');
        }

        $fileSize = filesize($temporaryPath);
        if (!is_int($fileSize) || $fileSize < 1 || $fileSize > self::MAX_IMAGE_BYTES) {
            return $this->failed('image_size_invalid');
        }

        if (!extension_loaded('curl') || !class_exists('CURLFile')) {
            return LocalClassifierResponseMapper::unavailable('http_client_unavailable');
        }

        $curl = curl_init(self::ENDPOINT);
        if ($curl === false) {
            return LocalClassifierResponseMapper::unavailable('http_client_initialization_failed');
        }

        $responseBody = '';
        $responseTooLarge = false;

        try {
            $postFields = [
                'file' => new CURLFile($temporaryPath, $mimeType, $allowedMimes[$mimeType]),
            ];

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
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
                return LocalClassifierResponseMapper::unavailable(
                    $responseTooLarge ? 'classifier_response_too_large' : 'classifier_transport_error'
                );
            }

            if ($httpStatus === 429) {
                return LocalClassifierResponseMapper::unavailable('classifier_rate_limited');
            }
            if (in_array($httpStatus, [400, 413, 415, 422], true)) {
                return $this->failed('classifier_rejected_image');
            }
            if ($httpStatus < 200 || $httpStatus >= 300) {
                return LocalClassifierResponseMapper::unavailable('classifier_request_failed');
            }

            try {
                $payload = json_decode($responseBody, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->failed('classifier_invalid_json');
            }

            if (!is_array($payload)) {
                return $this->failed('classifier_invalid_response');
            }

            return LocalClassifierResponseMapper::map($payload);
        } finally {
            curl_close($curl);
            unset($postFields, $responseBody);
        }
    }

    /** @return array<string, mixed> */
    private function failed(string $errorCode): array
    {
        return [
            'provider' => 'aidetect',
            'status' => 'failed',
            'signals' => [[
                'type' => 'ai_classifier',
                'outcome' => 'inconclusive',
                'explanation' => 'The local classifier could not complete a valid analysis. No conclusion about image authorship can be drawn.',
            ]],
            'error_code' => $errorCode,
            'metrics' => null,
        ];
    }
}
