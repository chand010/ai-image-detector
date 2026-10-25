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

if (!isset($_SESSION['analysis_owner_token']) || !is_string($_SESSION['analysis_owner_token'])) {
    session_regenerate_id(true);
    $_SESSION['analysis_owner_token'] = bin2hex(random_bytes(32));
}

function respond(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Upload result — AI Image Detector</title><link rel="stylesheet" href="/assets/app.css"></head><body><div class="site-shell"><header class="site-header"><a class="brand" href="/"><span class="brand-mark">AI</span><span>IMAGE INTELLIGENCE</span></a></header><main><section class="result-hero"><div><span class="eyebrow">Upload notice</span><h1>We could not <span class="accent-text">continue.</span></h1></div></section><div class="error-box" role="alert">' . $safeMessage . '</div><p style="margin-top:24px"><a class="btn btn-secondary" href="/" style="max-width:220px">Back to upload</a></p></main></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, 'Use the upload form to submit an image.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($sessionToken) ||
    $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    respond(403, 'The form session expired or could not be verified. Please try again.');
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    respond(400, 'No image was received.');
}

$file = $_FILES['image'];
if (!isset($file['error'], $file['tmp_name'], $file['size']) ||
    !is_int($file['error']) || !is_int($file['size']) || !is_string($file['tmp_name'])) {
    respond(400, 'The upload request is invalid.');
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the upload size limit.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Please choose an image first.',
        default => 'The upload failed. Please try again.'
    };
    respond(400, $message);
}

$maxBytes = 5 * 1024 * 1024;
if ($file['size'] < 1 || $file['size'] > $maxBytes ||
    !is_uploaded_file($file['tmp_name'])) {
    respond(413, 'The image must be between 1 byte and 5 MB.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = [
    'image/jpeg' => 'JPEG',
    'image/png' => 'PNG',
    'image/webp' => 'WebP',
];

if (!is_string($mime) || !isset($allowed[$mime])) {
    respond(415, 'Unsupported image format. Use JPEG, PNG, or WebP.');
}

$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1], $imageInfo['mime']) ||
    $imageInfo['mime'] !== $mime) {
    respond(400, 'The file is not a valid supported image.');
}

$width = (int) $imageInfo[0];
$height = (int) $imageInfo[1];
$maxPixels = 20_000_000;
if ($width < 1 || $height < 1 || $width > intdiv($maxPixels, $height) ||
    ($width * $height) > $maxPixels) {
    respond(413, 'The image dimensions are too large to process safely.');
}

$imageData = @file_get_contents($file['tmp_name']);
if ($imageData === false) {
    respond(400, 'The uploaded image could not be read.');
}
$decoded = @imagecreatefromstring($imageData);
unset($imageData);
if ($decoded === false) {
    respond(400, 'The image could not be decoded.');
}
imagedestroy($decoded);

$consentGiven = ($_POST['external_processing_consent'] ?? null) === 'yes';
$detection = [
    'provider' => 'smaug_local',
    'status' => 'unavailable',
    'label' => null,
    'confidence' => null,
    'signals' => [],
    'error_code' => 'local_classifier_not_started',
];

try {
    // Primary detection always runs locally; it does not require consent to
    // send the image to a third party and does not need an API token.
    require_once __DIR__ . '/../src/Detection/SmaugLocalClassifier.php';
    $classifier = new SmaugLocalClassifier();
    $detection = $classifier->analyze($file['tmp_name'], $mime);
} catch (Throwable $error) {
    error_log('AI Image Detector: local classifier failed unexpectedly.');
    $detection = [
        'provider' => 'smaug_local',
        'status' => 'unavailable',
        'label' => null,
        'confidence' => null,
        'signals' => [],
        'error_code' => 'local_classifier_internal_error',
    ];
}

if ($consentGiven) {
    try {
        // The optional provenance provider receives the image only after the
        // user opts in and the server-side feature flag/API key are configured.
        require_once __DIR__ . '/../src/Detection/OpenAIProvenanceProvider.php';
        $provenanceProvider = new OpenAIProvenanceProvider();
        $provenance = $provenanceProvider->analyze($file['tmp_name'], $mime);

        if (is_array($provenance['signals'] ?? null)) {
            $detection['signals'] = array_merge($detection['signals'] ?? [], $provenance['signals']);
        }

        if (($detection['status'] ?? null) !== 'completed' &&
            ($provenance['status'] ?? null) === 'completed') {
            $detection['provider'] = $provenance['provider'] ?? 'openai_content_provenance';
            $detection['status'] = 'completed';
            $detection['label'] = null;
            $detection['confidence'] = null;
            $detection['error_code'] = null;
        } elseif (($detection['status'] ?? null) !== 'completed' &&
            ($provenance['status'] ?? null) === 'inconclusive' &&
            ($detection['status'] ?? null) === 'unavailable') {
            $detection['status'] = 'inconclusive';
            $detection['error_code'] = null;
        }
    } catch (Throwable $error) {
        // Do not let an optional provider failure discard a local result.
        error_log('AI Image Detector: optional provenance provider failed.');
    }
} else {
    $detection['signals'] = is_array($detection['signals'] ?? null) ? $detection['signals'] : [];
}

try {
    // The original upload is not copied to application storage or MySQL.
    // Delete it before attempting database persistence.
    if (is_uploaded_file($file['tmp_name']) && !@unlink($file['tmp_name'])) {
        error_log('AI Image Detector: temporary upload cleanup failed.');
    }
} catch (Throwable $error) {
    error_log('AI Image Detector: temporary upload cleanup raised an error.');
}

$signalsJson = json_encode($detection['signals'] ?? [], JSON_THROW_ON_ERROR);

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = createDatabaseConnection();
    $statement = $pdo->prepare(
        'INSERT INTO analyses
            (owner_token_hash, image_mime, image_width, image_height, file_size_bytes,
             detection_status, detection_label, confidence, detection_provider,
             detection_error_code, detection_signals_json, external_processing_consent)
         VALUES
            (:owner_hash, :mime, :width, :height, :file_size,
             :status, :label, :confidence, :provider, :error_code, :signals, :consent)'
    );
    $statement->execute([
        'owner_hash' => hash('sha256', $_SESSION['analysis_owner_token']),
        'mime' => $mime,
        'width' => $width,
        'height' => $height,
        'file_size' => $file['size'],
        'status' => in_array($detection['status'] ?? null,
            ['not_configured', 'completed', 'inconclusive', 'unavailable', 'failed'], true)
                ? $detection['status'] : 'unavailable',
        'label' => $detection['label'] ?? null,
        'confidence' => isset($detection['confidence']) && is_numeric($detection['confidence'])
            ? max(0.0, min(1.0, (float) $detection['confidence']))
            : null,
        'provider' => is_string($detection['provider'] ?? null) ? $detection['provider'] : null,
        'error_code' => is_string($detection['error_code'] ?? null) &&
            preg_match('/^[a-z0-9_]{1,64}$/', $detection['error_code']) === 1
                ? $detection['error_code'] : null,
        'signals' => $signalsJson,
        'consent' => $consentGiven ? 1 : 0,
    ]);
    $analysisId = (int) $pdo->lastInsertId();
} catch (Throwable $exception) {
    // Log diagnostic class/state for operators without exposing SQL, paths,
    // uploaded content, exception messages, or credentials to visitors.
    $sqlState = $exception instanceof PDOException
        ? (string) ($exception->errorInfo[0] ?? $exception->getCode())
        : (string) $exception->getCode();
    error_log(sprintf(
        'AI Image Detector: metadata save failed (type=%s, sqlstate=%s).',
        get_class($exception),
        preg_match('/^[A-Z0-9]{5}$/', $sqlState) === 1 ? $sqlState : 'unknown'
    ));
    respond(503, 'The image was analyzed, but the result could not be saved. Please check the application logs and try again.');
}

header('Location: /result.php?id=' . $analysisId, true, 303);
exit;
