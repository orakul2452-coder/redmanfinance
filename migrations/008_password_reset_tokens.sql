USE `redmanfinance`;

ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_token_hash CHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_expires_at DATETIME NULL;
