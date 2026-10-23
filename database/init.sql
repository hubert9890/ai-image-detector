CREATE TABLE IF NOT EXISTS analysis (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    predicted_label VARCHAR(100) NOT NULL,
    is_ai_generated BOOLEAN NOT NULL,
    confidence DECIMAL(6,5) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_confidence
        CHECK (confidence >=0.00000 AND confidence <= 1.00000)
);

