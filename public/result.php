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

$analysis = null;
$errorMessage = null;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    http_response_code(400);
    $errorMessage = 'A valid analysis reference is required.';
} else {
    try {
        $pdo = createDatabaseConnection();
        $ownerToken = $_SESSION['analysis_owner_token'] ?? '';
        if (!is_string($ownerToken) || !preg_match('/^[a-f0-9]{64}$/', $ownerToken)) {
            http_response_code(404);
            $errorMessage = 'This analysis record could not be found.';
        } else {
            $statement = $pdo->prepare(
                'SELECT id, image_mime, image_width, image_height, file_size_bytes,
                        detection_status, detection_label, confidence, detection_provider,
                        detection_signals_json, external_processing_consent, created_at
                 FROM analyses
                 WHERE id = :id AND owner_token_hash = :owner_hash
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'owner_hash' => hash('sha256', $ownerToken),
            ]);
            $analysis = $statement->fetch();
        }

        if ($analysis === false) {
            http_response_code(404);
            $errorMessage = 'This analysis record could not be found.';
        }
    } catch (Throwable $error) {
        error_log('AI Image Detector: result retrieval failed.');
        http_response_code(503);
        $errorMessage = 'The result is temporarily unavailable. Please try again later.';
    }
}

function resultEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$signals = [];
if (is_array($analysis) && is_string($analysis['detection_signals_json'] ?? null)) {
    $decodedSignals = json_decode($analysis['detection_signals_json'], true);
    if (is_array($decodedSignals)) {
        $signals = $decodedSignals;
    }
}

$status = is_array($analysis) ? (string) ($analysis['detection_status'] ?? 'not_configured') : 'not_configured';
$label = is_array($analysis) && is_string($analysis['detection_label'] ?? null)
    ? (string) $analysis['detection_label']
    : null;
$score = is_array($analysis) && is_numeric($analysis['confidence'] ?? null)
    ? max(0.0, min(1.0, (float) $analysis['confidence']))
    : null;
$statusClass = match ($status) {
    'completed' => 'success',
    'inconclusive', 'unavailable', 'not_configured' => 'warning',
    default => 'error',
};
$statusText = match ($status) {
    'completed' => 'Analysis completed',
    'inconclusive' => 'Inconclusive',
    'unavailable' => 'Engine unavailable',
    'failed' => 'Analysis failed',
    default => 'Validation only',
};
$humanLabel = match ($label) {
    'likely_ai_generated' => 'Likely AI-generated',
    'likely_real' => 'Likely real image',
    default => null,
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#090b0a">
    <title>Analysis Result — AI Image Detector</title>
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
                    <span class="eyebrow">Analysis report</span>
                    <h1>Image <span class="accent-text">assessment.</span></h1>
                    <p class="lead">Results from validation and any configured analysis providers.</p>
                </div>
                <?php if ($analysis !== null && $errorMessage === null): ?>
                    <span class="status-pill <?= resultEscape($statusClass) ?>"><?= resultEscape($statusText) ?></span>
                <?php endif; ?>
            </section>

            <?php if ($errorMessage !== null): ?>
                <div class="error-box" role="alert">
                    <strong>We could not load this report.</strong>
                    <p style="margin:8px 0 0"><?= resultEscape($errorMessage) ?></p>
                    <p style="margin:12px 0 0"><a href="/">Return to image upload</a></p>
                </div>
            <?php elseif ($analysis !== null): ?>
                <div class="result-grid">
                    <section class="result-card" aria-labelledby="classification-heading">
                        <span class="result-label">Primary assessment</span>
                        <h2 id="classification-heading" class="result-value">
                            <?= $humanLabel !== null ? resultEscape($humanLabel) : 'No classification' ?>
                        </h2>
                        <?php if ($score !== null && $humanLabel !== null): ?>
                            <p class="subtle">Classifier score · <?= number_format($score * 100, 1) ?>%</p>
                            <div class="confidence-track" role="img" aria-label="Classifier score <?= number_format($score * 100, 1) ?> percent">
                                <div class="confidence-fill" style="width:<?= number_format($score * 100, 2, '.', '') ?>%"></div>
                            </div>
                            <p class="subtle">This is the model's reported score, not a calibrated probability that proves the image's origin.</p>
                        <?php elseif ($status === 'unavailable'): ?>
                            <p class="subtle">The classifier or provenance service is not configured, not enabled, or currently unreachable. The app did not invent a prediction.</p>
                        <?php elseif ($status === 'inconclusive'): ?>
                            <p class="subtle">The response did not provide a recognized class. No AI-origin conclusion can be drawn.</p>
                        <?php elseif ($status === 'failed'): ?>
                            <p class="subtle">The analysis could not be completed. Please try again with another supported image.</p>
                        <?php else: ?>
                            <p class="subtle">The image passed validation, but no classifier result is available for this scan.</p>
                        <?php endif; ?>
                        <div class="notice" style="margin-top:20px">An AI detector can make false positives and false negatives. Do not use this estimate as forensic proof or as the sole basis for a high-stakes decision.</div>
                    </section>

                    <section class="result-card" aria-labelledby="image-details-heading">
                        <span class="result-label">File details</span>
                        <h2 id="image-details-heading" style="margin:10px 0 0">Validated image</h2>
                        <div class="stats">
                            <div class="stat"><strong><?= resultEscape((string) $analysis['image_mime']) ?></strong><span>Format</span></div>
                            <div class="stat"><strong><?= (int) $analysis['image_width'] ?> × <?= (int) $analysis['image_height'] ?></strong><span>Dimensions</span></div>
                            <div class="stat"><strong><?= number_format(((int) $analysis['file_size_bytes']) / 1024, 1) ?> KB</strong><span>File size</span></div>
                        </div>
                        <p class="subtle" style="margin-top:20px">Analysis ID: <?= (int) $analysis['id'] ?><br>Saved at: <?= resultEscape((string) $analysis['created_at']) ?></p>
                        <?php if (is_string($analysis['detection_provider'] ?? null) && $analysis['detection_provider'] !== ''): ?>
                            <p class="subtle">Provider: <?= resultEscape((string) $analysis['detection_provider']) ?></p>
                        <?php endif; ?>
                    </section>

                    <section class="result-card full" aria-labelledby="signals-heading">
                        <span class="eyebrow">Evidence details</span>
                        <h2 id="signals-heading" style="margin-top:12px">Signals and provenance</h2>
                        <?php if ($signals !== []): ?>
                            <ul class="signal-list">
                                <?php foreach ($signals as $signal): ?>
                                    <?php if (is_array($signal)): ?>
                                        <li>
                                            <strong><?= resultEscape((string) ($signal['type'] ?? 'Unknown signal')) ?></strong>
                                            <p style="margin:5px 0 0"><?= resultEscape((string) ($signal['outcome'] ?? 'Unknown outcome')) ?></p>
                                            <p class="subtle" style="margin:5px 0 0"><?= resultEscape((string) ($signal['explanation'] ?? 'No explanation was supplied.')) ?></p>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="subtle">No provenance or classifier signals were returned for this scan.</p>
                        <?php endif; ?>
                        <p class="notice" style="margin-top:20px">A missing watermark or provenance signal does not prove an image is human-made. Model results depend on the detector's training data and may not generalize to every image generator or editing workflow.</p>
                    </section>

                    <section class="result-card full">
                        <h2>Privacy and next steps</h2>
                        <p class="subtle">The application saved image metadata and normalized analysis results only; it did not intentionally retain the original image. External analysis consent for this upload: <strong><?= (int) $analysis['external_processing_consent'] === 1 ? 'granted' : 'not granted' ?></strong>.</p>
                        <p><a class="btn btn-secondary" href="/">Analyze another image</a></p>
                    </section>
                </div>
            <?php endif; ?>
        </main>

        <footer class="site-footer">
            <div class="footer-inner">
                <span>AI Image Detector · Experimental analysis, not forensic proof</span>
                <span><a href="/history.php">View analysis history</a></span>
            </div>
        </footer>
    </div>
</body>
</html>
