CREATE TABLE IF NOT EXISTS analysis (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    image_type VARCHAR(100) ENUM ('jpg', 'jpeg', 'png', 'webp')  NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    predicted_label VARCHAR(100) ENUM ('AI-generated', 'Human') NOT NULL,
    is_ai_generated BOOLEAN NOT NULL,
    confidence DECIMAL(5,4) CHECK (confidence BETWEEN 0.0000 AND 1.0000) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

