USE `redmanfinance`;

-- Replace legacy RF numbers padded with leading zeroes.
-- A unique index on users.account_number must already exist.
UPDATE users
SET account_number = CONCAT('RF', FLOOR(1000000000 + RAND() * 9000000000))
WHERE account_number REGEXP '^RF0+[0-9]+$';

-- Confirm there are no remaining legacy values, missing values, or duplicates.
SELECT
    COUNT(*) AS legacy_zero_padded_numbers
FROM users
WHERE account_number REGEXP '^RF0+[0-9]+$';

SELECT
    COUNT(*) AS missing_account_numbers
FROM users
WHERE account_number IS NULL OR account_number = '';

SELECT account_number, COUNT(*) AS duplicate_count
FROM users
GROUP BY account_number
HAVING COUNT(*) > 1;