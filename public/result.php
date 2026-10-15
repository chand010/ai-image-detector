<?php
declare(strict_types=1);

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
                        detection_status, detection_label, confidence, created_at
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
                <h2 id="detection-heading">AI-origin assessment</h2>
                <p><strong>Not available yet.</strong> The AI detection engine has not been connected.</p>
                <p>This result must not be interpreted as evidence that the image is human-made or AI-generated. Passing image validation only means the file was readable and in a supported format.</p>
            </section>

            <p>Privacy: the application saved metadata only. It did not save the original image.</p>
        <?php endif; ?>
    </main>
</body>
</html>
