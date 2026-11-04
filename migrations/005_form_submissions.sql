CREATE TABLE IF NOT EXISTS form_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_name VARCHAR(128) NOT NULL DEFAULT 'contact',
    data_json LONGTEXT NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_form_name (form_name),
    INDEX idx_created_at (created_at)
);
