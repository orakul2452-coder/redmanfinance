-- Run once in TiDB SQL Editor before deploying the related PHP changes.
USE `redmanfinance`;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS account_number VARCHAR(16) NULL;

UPDATE users
SET account_number = CONCAT('RF', LPAD(id, 10, '0'))
WHERE account_number IS NULL OR account_number = '';

ALTER TABLE users
    ADD UNIQUE KEY uq_users_account_number (account_number);

CREATE TABLE IF NOT EXISTS account_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id CHAR(32) NOT NULL,
    reference VARCHAR(40) NOT NULL,
    sender_user_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    note VARCHAR(140) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account_transfers_request_id (request_id),
    UNIQUE KEY uq_account_transfers_reference (reference),
    KEY idx_account_transfers_sender (sender_user_id, created_at),
    KEY idx_account_transfers_recipient (recipient_user_id, created_at),
    CONSTRAINT chk_account_transfers_positive_amount CHECK (amount > 0),
    CONSTRAINT chk_account_transfers_different_users CHECK (sender_user_id <> recipient_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investment_strategies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    strategy_name VARCHAR(120) NOT NULL,
    strategy_type VARCHAR(40) NOT NULL DEFAULT 'ai',
    description TEXT NOT NULL,
    methodology TEXT NOT NULL,
    assets_covered VARCHAR(500) NOT NULL,
    risk_level VARCHAR(30) NOT NULL,
    minimum_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    maximum_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    term_days INT UNSIGNED NOT NULL DEFAULT 0,
    fee_description VARCHAR(500) NULL,
    evidence_description TEXT NOT NULL,
    evidence_url VARCHAR(1000) NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verification_notes TEXT NULL,
    verified_by_admin_id INT NULL,
    verified_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    is_investable TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_investment_strategies_status (verification_status, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investment_strategy_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    strategy_id BIGINT UNSIGNED NOT NULL,
    admin_id INT NULL,
    decision VARCHAR(20) NOT NULL,
    evidence_checked TEXT NOT NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_strategy_reviews_strategy (strategy_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;