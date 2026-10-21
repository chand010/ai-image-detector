<?php
declare(strict_types=1);

/**
 * Lightweight regression checks for security controls in the current prototype.
 *
 * These are source-level checks, not a substitute for HTTP integration tests,
 * penetration testing, or review of runtime configuration.
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
        $failures[] = "$name: could not read $path";
        return;
    }

    foreach ($needles as $needle) {
        $checks++;
        if (!str_contains($source, $needle)) {
            $failures[] = "$name: expected control not found in $path: $needle";
        }
    }

    foreach ($forbidden as $needle) {
        $checks++;
        if (str_contains($source, $needle)) {
            $failures[] = "$name: forbidden pattern found in $path: $needle";
        }
    }
}

checkSource(
    'Upload protections',
    'public/upload.php',
    [
        "hash_equals(",
        "hash('sha256', $_SESSION['analysis_owner_token'])",
        "is_uploaded_file(",
        "new finfo(FILEINFO_MIME_TYPE)",
        "'image/jpeg'",
        "'image/png'",
        "'image/webp'",
        '$maxBytes = 5 * 1024 * 1024;',
        '$maxPixels = 20_000_000;',
        'imagecreatefromstring(',
        "\$consentGiven = (\$_POST['external_processing_consent'] ?? null) === 'yes';",
        "\$provider->analyze(\$file['tmp_name'], \$mime)",
        "ENABLE_EXTERNAL_PROVENANCE",
        '$pdo->prepare(',
        'header(\'Location: /result.php?id=\' . $analysisId, true, 303)',
        "error_log('AI Image Detector: metadata save failed.')",
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
        'did not save the original image',
    ]
);

checkSource(
    'Session ownership schema',
    'database/schema.sql',
    [
        'owner_token_hash CHAR(64) NULL',
        'idx_analyses_owner_created (owner_token_hash, created_at)',
    ]
);

checkSource(
    'Database connection protections',
    'config/database.php',
    [
        'PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION',
        'PDO::ATTR_EMULATE_PREPARES => false',
        "getenv('DB_PASSWORD')",
    ],
    [
        'DB_PASSWORD=replace-with-a-long-random-password',
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
