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
    <meta name="theme-color" content="#090b0a">
    <title>AI Image Detector — Image Intelligence</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
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

        <section class="hero">
            <span class="eyebrow">Image analysis workspace</span>
            <h1>Look closer.<br><span class="accent-text">Question the image.</span></h1>
            <p class="lead">Validate image files, inspect supported provenance signals, and — when a classifier is configured — get an experimental AI-origin estimate. No single signal is proof of authorship.</p>
        </section>

        <main class="content-grid">
            <section class="panel" aria-labelledby="upload-heading">
                <span class="status-pill">Privacy-first workflow</span>
                <h2 id="upload-heading" style="margin-top:20px">Analyze an image</h2>
                <p class="subtle">Choose a JPEG, PNG, or WebP file up to 5 MB. The original upload is processed temporarily and deleted after analysis.</p>

                <form action="/upload.php" method="post" enctype="multipart/form-data" data-upload-form>
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

                    <div class="upload-zone" data-upload-zone>
                        <span class="upload-icon" aria-hidden="true">↑</span>
                        <div>
                            <strong>Drop your image here</strong>
                            <p class="subtle" style="margin:5px 0 10px">or <label for="image">browse files</label></p>
                            <input class="file-input" id="image" name="image" type="file"
                                   accept="image/jpeg,image/png,image/webp" required aria-describedby="file-help">
                            <p class="subtle" id="file-help" style="margin:0">JPEG · PNG · WebP · Max 5 MB</p>
                        </div>
                        <img class="preview" data-image-preview alt="Preview of the selected image">
                        <p class="file-name" data-file-name aria-live="polite">No image selected</p>
                    </div>

                    <div class="check-row">
                        <input id="external_processing_consent" name="external_processing_consent"
                               type="checkbox" value="yes">
                        <label for="external_processing_consent">
                            <strong>Allow external analysis for this upload</strong><br>
                            I agree that this image may be sent to a configured external AI classifier and/or OpenAI provenance service. This only happens when the corresponding server-side feature is enabled. Leave unchecked for local validation only.
                        </label>
                    </div>

                    <button class="btn" type="submit" data-submit-button>
                        <span class="spinner" aria-hidden="true"></span>
                        <span data-button-label>Validate and analyze image</span>
                        <span aria-hidden="true">↗</span>
                    </button>
                    <p class="privacy-note"><span aria-hidden="true">◆</span><span>Images are not stored as permanent files or in the database. Metadata and normalized analysis results may be saved to your browser-session history.</span></p>
                </form>
            </section>

            <aside class="side-stack" aria-label="How analysis works">
                <div class="mini-card">
                    <span class="eyebrow">01 / Validate</span>
                    <h3 style="margin-top:12px">Check the file</h3>
                    <p>Server-side checks inspect the real MIME type, dimensions, size, and whether the image can be decoded safely.</p>
                </div>
                <div class="mini-card">
                    <span class="eyebrow">02 / Assess</span>
                    <h3 style="margin-top:12px">Look for evidence</h3>
                    <p>Configured providers may return classifier scores or supported provenance signals. If unavailable, the app should say so clearly.</p>
                </div>
                <div class="mini-card">
                    <span class="eyebrow">03 / Interpret</span>
                    <h3 style="margin-top:12px">Treat results carefully</h3>
                    <p>AI-image detectors can make mistakes. “Likely real” does not prove human authorship, and a missing watermark proves nothing by itself.</p>
                </div>
            </aside>
        </main>

        <footer class="site-footer">
            <div class="footer-inner">
                <span>AI Image Detector · Learning and research prototype</span>
                <span>Raw PHP · Temporary image processing</span>
            </div>
        </footer>
    </div>
</body>
</html>
