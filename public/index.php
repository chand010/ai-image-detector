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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Image Detector — Prototype</title>
</head>
<body>
    <main>
        <h1>AI Image Detector</h1>
        <p><a href="/history.php">View recent analysis history</a></p>
        <p>This prototype checks image validity and, only when explicitly requested and enabled by the server, checks supported provenance signals. It is not a universal AI-image detector.</p>

        <form action="/upload.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

            <label for="image">Choose a JPEG, PNG, or WebP image (maximum 5 MB):</label>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>

            <p>
                <input id="external_processing_consent" name="external_processing_consent"
                       type="checkbox" value="yes">
                <label for="external_processing_consent">
                    I agree to send this image to OpenAI for supported provenance checks if the server has external processing enabled.
                </label>
            </p>
            <p>External processing is disabled by default. The image is not sent unless you select this option and the server is explicitly configured to enable it. A missing watermark or provenance signal does not prove an image is human-made.</p>
            <button type="submit">Validate and assess image</button>
        </form>
    </main>
</body>
</html>
