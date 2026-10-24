SET @email_verified_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'email_verified'
);
SET @migration_sql = IF(
    @email_verified_exists = 0,
    'ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @verification_token_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'email_verification_token_hash'
);
SET @migration_sql = IF(
    @verification_token_exists = 0,
    'ALTER TABLE users ADD COLUMN email_verification_token_hash CHAR(64) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @verification_expires_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'email_verification_expires_at'
);
SET @migration_sql = IF(
    @verification_expires_exists = 0,
    'ALTER TABLE users ADD COLUMN email_verification_expires_at DATETIME NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
