<?php
declare(strict_types=1);

/**
 * Small file-backed fixed-window limiter for this single-container learning app.
 * The directory must be private and writable only by the PHP worker account.
 * Do not trust X-Forwarded-For unless a trusted reverse proxy is configured.
 */
final class RateLimiter
{
    /**
     * Consume one request for a caller key.
     *
     * @return int|null Seconds until retry is allowed; null when allowed.
     */
    public static function consume(
        string $callerKey,
        string $stateDirectory,
        int $limit = 5,
        int $windowSeconds = 600
    ): ?int {
        if ($callerKey === '' || $limit < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException('Rate limiter configuration is invalid.');
        }

        if (!is_dir($stateDirectory) && !mkdir($stateDirectory, 0700, true) && !is_dir($stateDirectory)) {
            throw new RuntimeException('Rate limiter storage is unavailable.');
        }

        $directory = realpath($stateDirectory);
        if ($directory === false || !is_writable($directory)) {
            throw new RuntimeException('Rate limiter storage is unavailable.');
        }

        $statePath = $directory . DIRECTORY_SEPARATOR . 'upload-rate-state.json';
        $handle = fopen($statePath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Rate limiter storage is unavailable.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Rate limiter lock is unavailable.');
            }

            $raw = stream_get_contents($handle);
            $state = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Rate limiter state is invalid.');
                }
                $state = $decoded;
            }

            $now = time();
            $key = hash('sha256', $callerKey);
            $cutoff = $now - $windowSeconds;

            // Prune expired entries and bound state growth.
            foreach ($state as $storedKey => $timestamps) {
                if (!is_string($storedKey) || !is_array($timestamps)) {
                    unset($state[$storedKey]);
                    continue;
                }
                $timestamps = array_values(array_filter(
                    $timestamps,
                    static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $cutoff
                ));
                if ($timestamps === []) {
                    unset($state[$storedKey]);
                } else {
                    $state[$storedKey] = $timestamps;
                }
            }

            $attempts = $state[$key] ?? [];
            if (count($attempts) >= $limit) {
                $retryAfter = max(1, ($attempts[0] + $windowSeconds) - $now);
            } else {
                $attempts[] = $now;
                $state[$key] = $attempts;
                $retryAfter = null;
            }

            $encoded = json_encode($state, JSON_THROW_ON_ERROR);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded)) {
                throw new RuntimeException('Rate limiter state could not be saved.');
            }
            fflush($handle);
            chmod($statePath, 0600);

            return $retryAfter;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
