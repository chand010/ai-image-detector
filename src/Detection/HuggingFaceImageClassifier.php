<?php
declare(strict_types=1);

/**
 * Experimental hosted classifier adapter.
 *
 * This model is an indicator, not forensic proof. The upload is sent to the
 * configured inference provider only after per-upload consent and an explicit
 * server-side enable flag. No local copy is retained by this adapter.
 */
final class HuggingFaceImageClassifier
{
    private const DEFAULT_MODEL = 'umm-maybe/AI-image-detector';
    private const MAX_RESPONSE_BYTES = 262144;

    /**
     * @return array{
     *   provider: string,
     *   status: 'completed'|'inconclusive'|'unavailable'|'failed',
     *   label: ?string,
     *   confidence: ?float,
     *   signals: list<array{type: string, outcome: string, explanation: string}>,
     *   error_code: ?string
     * }
     */
    public function analyze(string $temporaryPath, string $mimeType): array
    {
        $model = getenv('HF_IMAGE_CLASSIFIER_MODEL');
        if (!is_string($model) || trim($model) === '') {
            $model = self::DEFAULT_MODEL;
        }

        if (getenv('ENABLE_EXTERNAL_CLASSIFIER') !== 'true') {
            return $this->result('unavailable', null, null, [], 'external_classifier_disabled', $model);
        }

        $token = getenv('HF_TOKEN');
        if (!is_string($token) || trim($token) === '') {
            return $this->result('unavailable', null, null, [], 'classifier_not_configured', $model);
        }

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true) ||
            !is_file($temporaryPath) || !is_readable($temporaryPath)) {
            return $this->result('failed', null, null, [], 'invalid_image_input', $model);
        }

        $size = filesize($temporaryPath);
        if (!is_int($size) || $size < 1 || $size > 5 * 1024 * 1024) {
            return $this->result('failed', null, null, [], 'image_size_invalid', $model);
        }

        if (!extension_loaded('curl')) {
            return $this->result('unavailable', null, null, [], 'http_client_unavailable', $model);
        }

        $imageBytes = file_get_contents($temporaryPath);
        if (!is_string($imageBytes)) {
            return $this->result('failed', null, null, [], 'image_read_failed', $model);
        }

        $endpoint = 'https://router.huggingface.co/hf-inference/models/' . $model;
        $curl = curl_init($endpoint);
        if ($curl === false) {
            unset($imageBytes, $token);
            return $this->result('unavailable', null, null, [], 'http_client_initialization_failed', $model);
        }

        $responseBody = '';
        $responseTooLarge = false;
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $imageBytes,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/octet-stream',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 30,
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
                return $this->result('unavailable', null, null, [], $responseTooLarge ? 'classifier_response_too_large' : 'classifier_transport_error', $model);
            }
            if ($httpStatus === 401 || $httpStatus === 403) {
                return $this->result('unavailable', null, null, [], 'classifier_auth_or_access_error', $model);
            }
            if ($httpStatus === 404 || $httpStatus === 429 || $httpStatus >= 500) {
                return $this->result('unavailable', null, null, [], 'classifier_service_unavailable', $model);
            }
            if ($httpStatus < 200 || $httpStatus >= 300) {
                return $this->result('failed', null, null, [], 'classifier_request_rejected', $model);
            }

            try {
                $payload = json_decode($responseBody, true, 16, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->result('failed', null, null, [], 'classifier_invalid_json', $model);
            }

            if (!is_array($payload) || !array_is_list($payload) || $payload === []) {
                return $this->result('inconclusive', null, null, [], 'classifier_unrecognized_response', $model);
            }

            $aiScore = null;
            $realScore = null;
            foreach ($payload as $prediction) {
                if (!is_array($prediction) || !is_string($prediction['label'] ?? null) ||
                    !is_numeric($prediction['score'] ?? null)) {
                    continue;
                }
                $label = strtolower(trim($prediction['label']));
                $score = (float) $prediction['score'];
                if (!is_finite($score) || $score < 0 || $score > 1) {
                    continue;
                }

                if (preg_match('/(artificial|ai.?generated|generated|synthetic|fake)/', $label) === 1) {
                    $aiScore = max($aiScore ?? 0.0, $score);
                } elseif (preg_match('/(human|real|authentic|natural|not.?ai|non.?ai)/', $label) === 1) {
                    $realScore = max($realScore ?? 0.0, $score);
                }
            }

            if ($aiScore === null && $realScore === null) {
                return $this->result('inconclusive', null, null, [], 'classifier_labels_unrecognized', $model);
            }

            if ($aiScore !== null && ($realScore === null || $aiScore >= $realScore)) {
                $label = 'likely_ai_generated';
                $score = $aiScore;
                $explanation = 'The experimental image classifier scored this image closer to its AI-generated class. This is a model estimate, not proof.';
            } else {
                $label = 'likely_real';
                $score = $realScore;
                $explanation = 'The experimental image classifier scored this image closer to its real-image class. This is not proof of human authorship.';
            }

            return $this->result('completed', $label, $score, [[
                'type' => 'ai_image_classifier',
                'outcome' => $label,
                'explanation' => $explanation,
            ]], null, $model);
        } finally {
            curl_close($curl);
            unset($imageBytes, $token, $responseBody);
        }
    }

    private function result(
        string $status,
        ?string $label,
        ?float $confidence,
        array $signals,
        ?string $errorCode,
        string $model
    ): array {
        return [
            'provider' => 'huggingface:' . $model,
            'status' => $status,
            'label' => $label,
            'confidence' => $confidence,
            'signals' => $signals,
            'error_code' => $errorCode,
        ];
    }
}
