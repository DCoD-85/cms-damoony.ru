CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    alias_name VARCHAR(64) NOT NULL DEFAULT 'www',
    provider VARCHAR(64) NOT NULL,
    method VARCHAR(64) NOT NULL,
    params_json LONGTEXT NULL,
    result_ok TINYINT(1) NOT NULL DEFAULT 1,
    error_text TEXT NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'cli',
    role_name VARCHAR(32) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_domain_alias (domain, alias_name),
    INDEX idx_provider_method (provider, method)
);
