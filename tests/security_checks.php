<?php
declare(strict_types=1);

/**
 * Lightweight regression checks for security controls in the current prototype.
 * These source-level checks are not a substitute for runtime testing or review.
 */

$root = dirname(__DIR__);
$checks = 0;
$failures = [];

function checkSource(string $name, string $path, array $needles, array $forbidden = []): void
{
    global $root, $checks, $failures;

    $fullPath = $root . '/' . $path;
    $source = file_get_contents($fullPath);
    if ($source === false) {
        $failures[] = sprintf('%s: could not read %s', $name, $path);
        return;
    }

    foreach ($needles as $needle) {
        $checks++;
        if (!str_contains($source, $needle)) {
            $failures[] = sprintf('%s: expected control not found in %s: %s', $name, $path, $needle);
        }
    }

    foreach ($forbidden as $needle) {
        $checks++;
        if (str_contains($source, $needle)) {
            $failures[] = sprintf('%s: forbidden pattern found in %s: %s', $name, $path, $needle);
        }
    }
}

checkSource(
    'Upload protections and local detector wiring',
    'public/upload.php',
    [
        'hash_equals(',
        'hash(\'sha256\', $_SESSION[\'analysis_owner_token\'])',
        'is_uploaded_file(',
        'new finfo(FILEINFO_MIME_TYPE)',
        "'image/jpeg'",
        "'image/png'",
        "'image/webp'",
        '$maxBytes = 5 * 1024 * 1024;',
        '$maxPixels = 20_000_000;',
        'imagecreatefromstring(',
        '$consentGiven = ($_POST[\'external_processing_consent\'] ?? null) === \'yes\';',
        '$classifier->analyze($file[\'tmp_name\'], $mime)',
        '$provenanceProvider->analyze($file[\'tmp_name\'], $mime)',
        '@unlink($file[\'tmp_name\'])',
        'temporary upload cleanup failed.',
        '$pdo->prepare(',
        'header(\'Location: /result.php?id=\' . $analysisId, true, 303)',
        'metadata save failed (type=%s, sqlstate=%s)',
        'SmaugLocalClassifier.php',
    ],
    [
        'move_uploaded_file(',
        'file_put_contents(',
    ]
);

checkSource(
    'History page protections',
    'public/history.php',
    [
        'ORDER BY created_at DESC, id DESC',
        'LIMIT 50',
        'WHERE owner_token_hash = :owner_hash',
        'htmlspecialchars(',
        "error_log('AI Image Detector: history retrieval failed.')",
    ]
);

checkSource(
    'Result page protections',
    'public/result.php',
    [
        '$pdo->prepare(',
        'FILTER_VALIDATE_INT',
        'owner_token_hash = :owner_hash',
        'htmlspecialchars(',
        'did not intentionally retain the original image',
        'external_processing_consent',
        'detection_error_code',
        'Detector not run',
        'Diagnostic code:',
        'No provenance or classifier signals were returned for this scan.',
    ]
);

checkSource(
    'Session ownership schema',
    'database/schema.sql',
    [
        'owner_token_hash CHAR(64) NULL',
        'detection_signals_json JSON NULL',
        'detection_error_code VARCHAR(64) NULL',
        'external_processing_consent TINYINT(1) NOT NULL DEFAULT 0',
        "'inconclusive'",
        "'unavailable'",
        'idx_analyses_owner_created (owner_token_hash, created_at)',
    ]
);

checkSource(
    'Database initializer migrations',
    'docker/init-database.sh',
    [
        'ensure_column detection_signals_json "JSON NULL"',
        'ensure_column detection_error_code "VARCHAR(64) NULL"',
        'ensure_column external_processing_consent "TINYINT(1) NOT NULL DEFAULT 0"',
        "'not_configured','completed','inconclusive','unavailable','failed'",
        'GRANT SELECT, INSERT, UPDATE ON $DB_NAME.*',
    ]
);

checkSource(
    'Database connection protections',
    'config/database.php',
    [
        'PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION',
        'PDO::ATTR_EMULATE_PREPARES => false',
        "getenv('DB_PASSWORD')",
        "getenv('DB_USER') ?: 'ai_image_app'",
    ],
    [
        'DB_PASSWORD=replace-with-a-long-random-password',
    ]
);

checkSource(
    'Local classifier safeguards',
    'src/Detection/SmaugLocalClassifier.php',
    [
        'ENABLE_LOCAL_CLASSIFIER',
        'proc_open(',
        "'bypass_shell' => true",
        'SMAUG_TIMEOUT_SECONDS',
        'local_inference_timeout',
        'MAX_IMAGE_BYTES',
    ],
    [
        'shell_exec(',
        'system(',
    ]
);

checkSource(
    'Python inference helper privacy and score validation',
    'scripts/smaug_detect.py',
    [
        'abacusai/Smaug-AI-Image-Detector',
        'snapshot_download(',
        'normalize_score(',
        'model_score_out_of_range',
        'local_model_load_or_inference_failed',
    ],
    [
        'requests.post(',
        'file.write(',
    ]
);

checkSource(
    'Frontend local processing and external consent',
    'public/index.php',
    [
        '/assets/app.css',
        '/assets/app.js',
        'external_processing_consent',
        'deleted after analysis',
        'data-image-preview',
        'The local Smaug classifier runs without this permission.',
    ]
);

if ($failures !== []) {
    fwrite(STDERR, "FAILED: " . count($failures) . " source-level security check(s).\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - $failure\n");
    }
    exit(1);
}

echo "PASS: $checks source-level security assertions passed.\n";
echo "Reminder: these checks inspect source text; they do not prove runtime security.\n";
