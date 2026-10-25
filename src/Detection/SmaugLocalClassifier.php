<?php
declare(strict_types=1);

/**
 * Runs the Smaug image-origin model locally through a small Python helper.
 *
 * Image bytes are passed only as the existing PHP temporary-file path. The
 * helper never writes a copy of the uploaded image; its model cache contains
 * model artifacts only. This is a prediction, not forensic proof.
 */
final class SmaugLocalClassifier
{
    private const MAX_IMAGE_BYTES = 5242880;
    private const MAX_STDOUT_BYTES = 262144;
    private const MAX_STDERR_BYTES = 16384;

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
        if (getenv('ENABLE_LOCAL_CLASSIFIER') === 'false') {
            return $this->result('unavailable', null, null, [], 'local_classifier_disabled');
        }

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true) ||
            !is_file($temporaryPath) || !is_readable($temporaryPath)) {
            return $this->result('failed', null, null, [], 'invalid_image_input');
        }

        $size = filesize($temporaryPath);
        if (!is_int($size) || $size < 1 || $size > self::MAX_IMAGE_BYTES) {
            return $this->result('failed', null, null, [], 'image_size_invalid');
        }

        $python = getenv('SMAUG_PYTHON') ?: '/opt/ai-detector-venv/bin/python';
        $script = __DIR__ . '/../../scripts/smaug_detect.py';
        if (!is_executable($python) || !is_file($script) || !function_exists('proc_open')) {
            return $this->result('unavailable', null, null, [], 'local_runtime_not_configured');
        }

        $pipes = [];
        $process = @proc_open(
            [$python, $script, $temporaryPath],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            return $this->result('unavailable', null, null, [], 'local_runtime_start_failed');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startedAt = microtime(true);
        $timeout = getenv('SMAUG_TIMEOUT_SECONDS');
        $timeoutSeconds = is_string($timeout) && ctype_digit($timeout)
            ? max(30, min(600, (int) $timeout))
            : 300;
        $exitCode = null;
        $timedOut = false;

        try {
            while (true) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);

                if (strlen($stdout) > self::MAX_STDOUT_BYTES ||
                    strlen($stderr) > self::MAX_STDERR_BYTES) {
                    proc_terminate($process, 15);
                    return $this->result('unavailable', null, null, [], 'local_runtime_output_limit');
                }

                $processStatus = proc_get_status($process);
                if (!$processStatus['running']) {
                    $exitCode = (int) $processStatus['exitcode'];
                    break;
                }

                if ((microtime(true) - $startedAt) > $timeoutSeconds) {
                    $timedOut = true;
                    proc_terminate($process, 15);
                    usleep(250000);
                    $afterTerminate = proc_get_status($process);
                    if ($afterTerminate['running']) {
                        proc_terminate($process, 9);
                    }
                    break;
                }

                usleep(100000);
            }

            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        if ($timedOut) {
            return $this->result('unavailable', null, null, [], 'local_inference_timeout');
        }

        if ($exitCode !== 0 || strlen($stdout) > self::MAX_STDOUT_BYTES) {
            error_log('AI Image Detector: local inference helper failed.');
            return $this->result('unavailable', null, null, [], 'local_inference_failed');
        }

        try {
            $payload = json_decode(trim($stdout), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('AI Image Detector: local inference helper returned invalid JSON.');
            return $this->result('unavailable', null, null, [], 'local_inference_invalid_response');
        }

        if (!is_array($payload) ||
            !is_string($payload['status'] ?? null) ||
            !in_array($payload['status'], ['completed', 'inconclusive', 'unavailable', 'failed'], true)) {
            return $this->result('unavailable', null, null, [], 'local_inference_invalid_response');
        }

        $label = $payload['label'] ?? null;
        $confidence = $payload['confidence'] ?? null;
        $signals = $payload['signals'] ?? [];
        if ($label !== null && !in_array($label, ['likely_ai_generated', 'likely_real'], true)) {
            return $this->result('inconclusive', null, null, [], 'local_inference_label_invalid');
        }
        if ($confidence !== null && (!is_numeric($confidence) ||
            !is_finite((float) $confidence) || (float) $confidence < 0 || (float) $confidence > 1)) {
            return $this->result('inconclusive', null, null, [], 'local_inference_score_invalid');
        }
        if (!is_array($signals) || !array_is_list($signals)) {
            $signals = [];
        }

        return [
            'provider' => 'smaug_local',
            'status' => $payload['status'],
            'label' => $label,
            'confidence' => $confidence === null ? null : (float) $confidence,
            'signals' => $signals,
            'error_code' => is_string($payload['error_code'] ?? null) ? $payload['error_code'] : null,
        ];
    }

    private function result(
        string $status,
        ?string $label,
        ?float $confidence,
        array $signals,
        ?string $errorCode
    ): array {
        return [
            'provider' => 'smaug_local',
            'status' => $status,
            'label' => $label,
            'confidence' => $confidence,
            'signals' => $signals,
            'error_code' => $errorCode,
        ];
    }
}
