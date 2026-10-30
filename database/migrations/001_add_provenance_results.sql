-- Upgrade an existing AI Image Detector database for consent-gated provenance results.
-- Back up the database before applying this migration.
USE ai_image_detector;

ALTER TABLE analyses
    MODIFY detection_status ENUM(
        'not_configured',
        'completed',
        'inconclusive',
        'unavailable',
        'failed'
    ) NOT NULL DEFAULT 'not_configured',
    ADD COLUMN detection_provider VARCHAR(64) NULL AFTER confidence,
    ADD COLUMN detection_signals_json JSON NULL AFTER detection_provider,
    ADD COLUMN external_processing_consent TINYINT(1) NOT NULL DEFAULT 0 AFTER detection_signals_json;
