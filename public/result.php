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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analysis Result — AI Image Detector</title>
</head>
<body>
    <main>
        <h1>Analysis Result</h1>
        <p><a href="/">Analyze another image</a> | <a href="/history.php">View history</a></p>

        <?php if ($errorMessage !== null): ?>
            <p role="alert"><?= resultEscape($errorMessage) ?></p>
        <?php elseif ($analysis !== null): ?>
            <section aria-labelledby="validation-heading">
                <h2 id="validation-heading">Image validation</h2>
                <p>The file passed the application's format and image-decoding checks.</p>
                <dl>
                    <dt>Record ID</dt>
                    <dd><?= (int) $analysis['id'] ?></dd>
                    <dt>Format</dt>
                    <dd><?= resultEscape((string) $analysis['image_mime']) ?></dd>
                    <dt>Dimensions</dt>
                    <dd><?= (int) $analysis['image_width'] ?> × <?= (int) $analysis['image_height'] ?> pixels</dd>
                    <dt>File size</dt>
                    <dd><?= number_format(((int) $analysis['file_size_bytes']) / 1024, 1) ?> KB</dd>
                    <dt>Saved at</dt>
                    <dd><?= resultEscape((string) $analysis['created_at']) ?></dd>
                </dl>
            </section>

            <section aria-labelledby="detection-heading">
                <h2 id="detection-heading">AI-origin provenance assessment</h2>
                <?php if ($analysis['detection_status'] === 'completed' && $signals !== []): ?>
                    <p><strong>Supported provenance check completed.</strong> This checks only the supported signals below, not every possible AI generator.</p>
                    <ul>
                        <?php foreach ($signals as $signal): ?>
                            <?php if (is_array($signal)): ?>
                                <li>
                                    <strong><?= resultEscape((string) ($signal['type'] ?? 'unknown')) ?>:</strong>
                                    <?= resultEscape((string) ($signal['outcome'] ?? 'unknown')) ?> —
                                    <?= resultEscape((string) ($signal['explanation'] ?? 'No explanation supplied.')) ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($analysis['detection_status'] === 'inconclusive'): ?>
                    <p><strong>Inconclusive.</strong> No supported signal could be reported. This does not establish whether the image was AI-generated.</p>
                <?php elseif ($analysis['detection_status'] === 'unavailable'): ?>
                    <p><strong>Provenance service unavailable.</strong> No AI-origin conclusion can be drawn. The provider may be disabled, unconfigured, rate-limited, or inaccessible.</p>
                <?php elseif ($analysis['detection_status'] === 'failed'): ?>
                    <p><strong>Provenance check failed.</strong> No AI-origin conclusion can be drawn from this attempt.</p>
                <?php else: ?>
                    <p><strong>Not run.</strong> External provenance was not requested or is disabled by server configuration.</p>
                <?php endif; ?>
                <p>A “not detected” result means no supported signal was found. It is not proof that the image is human-made. The app does not currently use a general-purpose AI image classifier.</p>
            </section>

            <p>Privacy: the application saved metadata and signal outcomes only. It did not save the original image. External processing consent for this upload: <?= (int) $analysis['external_processing_consent'] === 1 ? 'yes' : 'no' ?>.</p>
        <?php endif; ?>
    </main>
</body>
</html>
