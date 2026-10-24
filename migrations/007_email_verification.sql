USE `redmanfinance`;

-- Run each ALTER separately in SQL editors that execute only one statement at a time.
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN IF NOT EXISTS pending_email VARCHAR(200) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_token_hash CHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_expires_at DATETIME NULL;

ALTER TABLE users
    MODIFY COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0;

-- Confirm the columns above exist before creating this index.
CREATE INDEX idx_users_email_verification_token
    ON users (email_verification_token_hash);
