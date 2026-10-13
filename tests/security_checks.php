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
        "is_uploaded_file(",
        "new finfo(FILEINFO_MIME_TYPE)",
        "'image/jpeg'",
        "'image/png'",
        "'image/webp'",
        '$maxBytes = 5 * 1024 * 1024;',
        '$maxPixels = 20_000_000;',
        'imagecreatefromstring(',
        '$pdo->prepare(',
        "header('Location: /result.php?id=' . $analysisId, true, 303)",
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
        'htmlspecialchars(',
        'AI detection engine has not been connected.',
        'did not save the original image',
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
