-- Add role to users if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(16) NOT NULL DEFAULT 'user';

-- Create initial admin user (password: admin123)
INSERT IGNORE INTO users (login, email, password_hash, role, status)
VALUES (
    'admin',
    'admin@local.dev',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'active'
);
