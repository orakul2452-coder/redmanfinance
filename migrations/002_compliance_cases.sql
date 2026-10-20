ALTER TABLE users ADD COLUMN IF NOT EXISTS compliance_case_reference VARCHAR(32) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS compliance_id_front_path VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS compliance_id_back_path VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS compliance_address_path VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS compliance_submitted_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS compliance_case_events (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_compliance_case_events_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;