# redmanfinance
# redmanfinance

## Email verification deployment

Before deploying the email-verification changes, apply `migrations/002_email_verification.sql` to the `redmanfinance` database. The migration adds any missing verification columns and can be safely rerun if some columns already exist. Existing accounts remain verified; new registrations must verify their email before login.

Before deploying password-reset changes, apply `migrations/003_password_reset_tokens.sql` to the same database. It adds only missing token fields and can be safely rerun. Password recovery now emails a one-time link that expires after one hour instead of emailing the account password. Password values continue to use the existing storage format.

The Render service uses `APP_BASE_URL` to generate links to `/trade/verify-email.php`. Keep it set to the public HTTPS origin for the deployed service. SMTP delivery uses `SMTP_HOST`, `SMTP_USERNAME`, and `SMTP_PASSWORD`; `SMTP_PORT` defaults to `465` and `SMTP_ENCRYPTION` defaults to `ssl`. Use the host and port/security combination specified by your email provider (`587` with `tls` is common). Users can request a fresh link from the login page or `trade/resend-verification.php`.

## Risk management migration

Apply `migrations/001_risk_management.sql` once to the application database before deploying the risk-management PHP changes. It adds explicit KYC and hold state to users, a vault balance, transaction and investment pause fields, and the global settings, per-user overrides, and hold-event audit tables. The script seeds defaults of a $100 minimum withdrawal, a $600,000 large-deposit/balance trigger, $900,000 approved transaction volume, a 14-day release cooldown, and a 30-day new-account window.

The migration is additive but is not repeatable because its `ALTER TABLE` statements add columns. Back up the target database and confirm the migration has not already been applied before running it. It does not import the legacy seed rows in `database.sql`.

Run local database-independent risk checks with:

```powershell
php tests/risk_rules_test.php
```

The application also requires PHP `mysqli` and a MySQL-compatible database for integration testing; those are not available in the current local CLI environment.
