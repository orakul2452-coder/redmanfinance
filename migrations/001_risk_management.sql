ALTER TABLE users
    ADD COLUMN kyc_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
    ADD COLUMN kyc_reviewed_at DATETIME NULL,
    ADD COLUMN kyc_reviewed_by INT NULL,
    ADD COLUMN is_on_hold TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN hold_reason VARCHAR(500) NULL,
    ADD COLUMN hold_started_at DATETIME NULL,
    ADD COLUMN hold_requirements_met_at DATETIME NULL,
    ADD COLUMN hold_release_date DATETIME NULL,
    ADD COLUMN vault_balance DOUBLE NOT NULL DEFAULT 0;

ALTER TABLE btc
    ADD COLUMN risk_held TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE investment
    ADD COLUMN risk_paused_at DATETIME NULL,
    ADD COLUMN risk_paused_seconds BIGINT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value VARCHAR(100) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_risk_overrides (
    user_id INT NOT NULL,
    hold_trigger_deposit_amount DOUBLE NULL,
    required_transaction_volume DOUBLE NULL,
    hold_cooldown_days INT NULL,
    hold_new_account_days INT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_user_risk_overrides_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_hold_events (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    details JSON NULL,
    actor_admin_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_account_hold_events_user_created (user_id, created_at),
    KEY idx_account_hold_events_actor (actor_admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('min_withdrawal_amount', '100'),
    ('hold_trigger_deposit_amount', '600000'),
    ('required_transaction_volume', '900000'),
    ('hold_cooldown_days', '14'),
    ('hold_new_account_days', '30')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
