-- AI Image Detector database schema
-- Apply manually after MySQL is initialized and a restricted application user exists.
-- Do not store uploaded image bytes or original filesystem paths in this table.

CREATE DATABASE IF NOT EXISTS ai_image_detector
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ai_image_detector;

CREATE TABLE IF NOT EXISTS analyses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_token_hash CHAR(64) NULL,
    image_mime VARCHAR(32) NOT NULL,
    image_width INT UNSIGNED NOT NULL,
    image_height INT UNSIGNED NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    detection_status ENUM(
        'not_configured',
        'completed',
        'failed'
    ) NOT NULL DEFAULT 'not_configured',
    detection_label VARCHAR(64) NULL,
    confidence DECIMAL(5,4) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_analyses_created_at (created_at),
    INDEX idx_analyses_owner_created (owner_token_hash, created_at),
    CONSTRAINT chk_analyses_dimensions CHECK (
        image_width > 0 AND image_height > 0
    ),
    CONSTRAINT chk_analyses_file_size CHECK (
        file_size_bytes > 0 AND file_size_bytes <= 5242880
    ),
    CONSTRAINT chk_analyses_confidence CHECK (
        confidence IS NULL OR (confidence >= 0 AND confidence <= 1)
    )
) ENGINE=InnoDB;
