<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../config/database.php';

$analyses = [];
$errorMessage = null;

$ownerToken = $_SESSION['analysis_owner_token'] ?? '';

try {
    if (is_string($ownerToken) && preg_match('/^[a-f0-9]{64}$/', $ownerToken)) {
        $pdo = createDatabaseConnection();
        $statement = $pdo->prepare(
            'SELECT id, image_mime, image_width, image_height, file_size_bytes,
                    detection_status, detection_label, confidence, created_at
             FROM analyses
             WHERE owner_token_hash = :owner_hash
             ORDER BY created_at DESC, id DESC
             LIMIT 50'
        );
        $statement->execute(['owner_hash' => hash('sha256', $ownerToken)]);
        $analyses = $statement->fetchAll();
    }
} catch (Throwable $error) {
    error_log('AI Image Detector: history retrieval failed.');
    http_response_code(503);
    $errorMessage = 'Analysis history is temporarily unavailable. Please try again later.';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatBytes(int $bytes): string
{
    return number_format($bytes / 1024, 1) . ' KB';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analysis History — AI Image Detector</title>
</head>
<body>
    <main>
        <h1>Analysis History</h1>
        <p><a href="/">Return to image upload</a></p>
        <p>Showing up to 50 analysis records from this browser session. Other sessions cannot view these records. Original image files are not stored.</p>

        <?php if ($errorMessage !== null): ?>
            <p role="alert"><?= escapeHtml($errorMessage) ?></p>
        <?php elseif ($analyses === []): ?>
            <p>No analysis records have been saved yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Date (UTC/server time)</th>
                        <th scope="col">Image details</th>
                        <th scope="col">Status</th>
                        <th scope="col">Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyses as $analysis): ?>
                        <tr>
                            <td><?= (int) $analysis['id'] ?></td>
                            <td><?= escapeHtml((string) $analysis['created_at']) ?></td>
                            <td>
                                <?= escapeHtml((string) $analysis['image_mime']) ?>,
                                <?= (int) $analysis['image_width'] ?> × <?= (int) $analysis['image_height'] ?>,
                                <?= escapeHtml(formatBytes((int) $analysis['file_size_bytes'])) ?>
                            </td>
                            <td><?= escapeHtml((string) $analysis['detection_status']) ?></td>
                            <td>
                                <?php if ($analysis['detection_label'] !== null): ?>
                                    <?= escapeHtml((string) $analysis['detection_label']) ?>
                                <?php else: ?>
                                    Not available
                                <?php endif; ?>
                                <?php if ($analysis['confidence'] !== null): ?>
                                    (<?= escapeHtml((string) $analysis['confidence']) ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
