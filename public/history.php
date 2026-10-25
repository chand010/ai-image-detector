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
    <meta name="theme-color" content="#090b0a">
    <title>Analysis History — AI Image Detector</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <div class="site-shell">
        <header class="site-header">
            <a class="brand" href="/" aria-label="AI Image Detector home">
                <span class="brand-mark" aria-hidden="true">AI</span>
                <span>IMAGE INTELLIGENCE</span>
            </a>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="/">Analyze image</a>
                <a href="/history.php">Analysis history</a>
            </nav>
        </header>
        <main>
            <section class="result-hero">
                <div>
                    <span class="eyebrow">Your workspace</span>
                    <h1>Analysis <span class="accent-text">history.</span></h1>
                    <p class="lead">Up to 50 reports saved to this browser session. Original image files are not stored.</p>
                </div>
                <a class="btn btn-secondary" href="/">+ New analysis</a>
            </section>

            <?php if ($errorMessage !== null): ?>
                <div class="error-box" role="alert"><?= escapeHtml($errorMessage) ?></div>
            <?php elseif ($analyses === []): ?>
                <section class="panel">
                    <span class="upload-icon" aria-hidden="true">↗</span>
                    <h2 style="margin-top:18px">No scans yet</h2>
                    <p class="subtle">Your image analysis reports will appear here after you run a scan.</p>
                    <a class="btn" href="/" style="max-width:260px">Analyze your first image</a>
                </section>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Report</th>
                                <th scope="col">Date (server time)</th>
                                <th scope="col">Image</th>
                                <th scope="col">Status</th>
                                <th scope="col">Assessment</th>
                                <th scope="col">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analyses as $analysis): ?>
                                <tr>
                                    <td>#<?= (int) $analysis['id'] ?></td>
                                    <td><?= escapeHtml((string) $analysis['created_at']) ?></td>
                                    <td>
                                        <?= escapeHtml((string) $analysis['image_mime']) ?><br>
                                        <span class="subtle"><?= (int) $analysis['image_width'] ?> × <?= (int) $analysis['image_height'] ?> · <?= escapeHtml(formatBytes((int) $analysis['file_size_bytes'])) ?></span>
                                    </td>
                                    <td><?= escapeHtml((string) $analysis['detection_status']) ?></td>
                                    <td>
                                        <?php if (($analysis['detection_label'] ?? null) === 'likely_ai_generated'): ?>
                                            Likely AI-generated
                                        <?php elseif (($analysis['detection_label'] ?? null) === 'likely_real'): ?>
                                            Likely real image
                                        <?php elseif (($analysis['detection_label'] ?? null) !== null): ?>
                                            <?= escapeHtml((string) $analysis['detection_label']) ?>
                                        <?php else: ?>
                                            <span class="subtle">No classification</span>
                                        <?php endif; ?>
                                        <?php if (is_numeric($analysis['confidence'] ?? null)): ?>
                                            <br><span class="subtle">Score <?= number_format((float) $analysis['confidence'] * 100, 1) ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="/result.php?id=<?= (int) $analysis['id'] ?>">Open report →</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
        <footer class="site-footer">
            <div class="footer-inner">
                <span>AI Image Detector · Session-scoped history</span>
                <span><a href="/">Back to analysis</a></span>
            </div>
        </footer>
    </div>
</body>
</html>
