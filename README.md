# redmanfinance
# redmanfinance

## Risk management migration

Apply `migrations/001_risk_management.sql` once to the application database before deploying the risk-management PHP changes. It adds explicit KYC and hold state to users, a vault balance, transaction and investment pause fields, and the global settings, per-user overrides, and hold-event audit tables. The script seeds defaults of a $100 minimum withdrawal, a $600,000 large-deposit/balance trigger, $900,000 approved transaction volume, a 14-day release cooldown, and a 30-day new-account window.

The migration is additive but is not repeatable because its `ALTER TABLE` statements add columns. Back up the target database and confirm the migration has not already been applied before running it. It does not import the legacy seed rows in `database.sql`.

Run local database-independent risk checks with:

```powershell
php tests/risk_rules_test.php
```

The application also requires PHP `mysqli` and a MySQL-compatible database for integration testing; those are not available in the current local CLI environment.
