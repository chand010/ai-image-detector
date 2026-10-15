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
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Upload result</title><body><main><h1>Upload result</h1><p>' . $safeMessage . '</p><p><a href="/">Back</a></p></main></body></html>';
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

// Save only validated metadata. The uploaded image itself is never stored.
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = createDatabaseConnection();
    $statement = $pdo->prepare(
        'INSERT INTO analyses (owner_token_hash, image_mime, image_width, image_height, file_size_bytes, detection_status)
         VALUES (:owner_hash, :mime, :width, :height, :file_size, :status)'
    );
    $statement->execute([
        'owner_hash' => hash('sha256', $_SESSION['analysis_owner_token']),
        'mime' => $mime,
        'width' => $width,
        'height' => $height,
        'file_size' => $file['size'],
        'status' => 'not_configured',
    ]);
    $analysisId = (int) $pdo->lastInsertId();
} catch (Throwable $exception) {
    // Log only a generic event; do not expose database details to visitors.
    error_log('AI Image Detector: metadata save failed.');
    respond(503, 'The image passed validation, but the result could not be saved. Please try again later.');
}

header('Location: /result.php?id=' . $analysisId, true, 303);
exit;
