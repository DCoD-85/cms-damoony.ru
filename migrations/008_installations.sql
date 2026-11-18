-- Installations и персональные сборки (TTL) для ЛК developer

CREATE TABLE IF NOT EXISTS installations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(253) NOT NULL,
    assembly VARCHAR(64) NOT NULL DEFAULT 'minimal',
    langs VARCHAR(64) NOT NULL DEFAULT 'ru,en',
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    token_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    install_count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_install_token (token_hash),
    KEY idx_install_account (account_id),
    KEY idx_install_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS builds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    installation_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(32) NOT NULL DEFAULT '',
    file VARCHAR(255) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    size BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    download_count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_build_installation (installation_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
