SET @password_reset_token_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'password_reset_token_hash'
);
SET @migration_sql = IF(
    @password_reset_token_exists = 0,
    'ALTER TABLE users ADD COLUMN password_reset_token_hash CHAR(64) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @password_reset_expires_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'password_reset_expires_at'
);
SET @migration_sql = IF(
    @password_reset_expires_exists = 0,
    'ALTER TABLE users ADD COLUMN password_reset_expires_at DATETIME NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
