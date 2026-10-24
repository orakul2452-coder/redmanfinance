-- Apply once before deploying the risk-control PHP changes.
-- Compatible with MySQL 8+ and TiDB MySQL compatibility mode.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_on_hold TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS hold_reason VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS hold_started_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS hold_requirements_met_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS hold_release_date DATETIME NULL,
    ADD COLUMN IF NOT EXISTS vault_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS kyc_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
    ADD COLUMN IF NOT EXISTS kyc_verified_at DATETIME NULL;

ALTER TABLE investment
    ADD COLUMN IF NOT EXISTS risk_paused_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS risk_paused_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE btc
    ADD COLUMN IF NOT EXISTS risk_held TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('min_withdrawal_amount', '100.00'),
    ('hold_trigger_deposit_amount', '600000.00'),
    ('required_transaction_volume', '900000.00'),
    ('hold_cooldown_days', '14'),
    ('hold_new_account_days', '30')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS user_risk_overrides (
    user_id INT NOT NULL PRIMARY KEY,
    hold_trigger_deposit_amount DECIMAL(15,2) NULL,
    required_transaction_volume DECIMAL(15,2) NULL,
    hold_cooldown_days INT UNSIGNED NULL,
    hold_new_account_days INT UNSIGNED NULL,
    updated_by_admin_id INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_risk_overrides_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_hold_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    reason VARCHAR(255) NULL,
    details LONGTEXT NULL,
    actor_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_account_hold_events_user (user_id, created_at),
    CONSTRAINT fk_account_hold_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;