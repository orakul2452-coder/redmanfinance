USE `redmanfinance`;

CREATE TABLE IF NOT EXISTS admin_alert_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(190) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('queued', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    UNIQUE KEY uq_admin_alert_event (event_key),
    KEY idx_admin_alert_pending (status, available_at, id),
    KEY idx_admin_alert_type_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
