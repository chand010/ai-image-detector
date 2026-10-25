<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Detection/SmaugLocalClassifier.php';

putenv('ENABLE_LOCAL_CLASSIFIER=false');
$classifier = new SmaugLocalClassifier();
$result = $classifier->analyze('/not/a/real/image.png', 'image/png');

if (($result['status'] ?? null) !== 'unavailable' ||
    ($result['error_code'] ?? null) !== 'local_classifier_disabled' ||
    ($result['label'] ?? null) !== null) {
    fwrite(STDERR, "FAIL: disabled local detector must return unavailable without inventing a label.\n");
    exit(1);
}

echo "Local detector disabled-mode test passed.\n";
