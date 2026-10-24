# redmanfinance
# redmanfinance

## Account email delivery

Email verification columns are created by `migrations/007_email_verification.sql`. Before deploying secure password-reset links, apply `migrations/008_password_reset_tokens.sql` to the `redmanfinance` database. It adds only the missing reset-token columns and can be rerun safely.

The Render service uses `APP_BASE_URL` to generate account links. This service currently uses Gmail SMTP with `SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_PORT`, and `SMTP_PORT_FALLBACK`; set `MAIL_FROM_ADDRESS` to the same Gmail sender (`redmanfinance.onrender.com@gmail.com`). Render Free web services block outbound traffic on ports 25, 465, and 587, so this Gmail SMTP setup requires a paid Render web service. No email-provider API key is needed. Once on a plan that permits SMTP egress, delivery tries `SMTP_PORT` first (587 with STARTTLS by default) and then `SMTP_PORT_FALLBACK` (465 with implicit TLS). Delivery failures are recorded in application logs without logging the SMTP password.

## Risk management migration

Apply `migrations/001_risk_management.sql` once to the application database before deploying the risk-management PHP changes. It adds explicit KYC and hold state to users, a vault balance, transaction and investment pause fields, and the global settings, per-user overrides, and hold-event audit tables. The script seeds defaults of a $100 minimum withdrawal, a $600,000 large-deposit/balance trigger, $900,000 approved transaction volume, a 14-day release cooldown, and a 30-day new-account window.

The migration is additive but is not repeatable because its `ALTER TABLE` statements add columns. Back up the target database and confirm the migration has not already been applied before running it. It does not import the legacy seed rows in `database.sql`.

Run local database-independent risk checks with:

```powershell
php tests/risk_rules_test.php
```

## Admin finance alerts

Apply `migrations/006_admin_alert_outbox.sql` before deploying admin finance alerts. Configure `MAKE_FINANCE_ALERT_WEBHOOK_URL` as a secret environment variable on Render, or enter the Make custom webhook URL when starting the local server with `start-local.ps1`. If API-key authentication is enabled on the Make webhook, set `MAKE_FINANCE_ALERT_WEBHOOK_API_KEY` in the same secret environment. Make is attempted first; if it is unset or returns an error, the app sends the alert by SMTP to `ADMIN_ALERT_EMAIL` (or `SMTP_USERNAME` when no recipient override is set). Never commit webhook, API-key, or SMTP secrets.

The app queues alerts for investment deposit submissions, wallet top-ups, withdrawals, and compliance-case submissions. The Make scenario should accept JSON, send an admin email, then use a **Webhook response** module after Gmail to return JSON `{"delivered":true}` with HTTP 200. The app only treats that explicit response as success; default webhook acknowledgments or failed Make runs trigger the SMTP fallback. Do not include uploaded documents or credentials in alert emails. Retry queued alerts with `php tools/send-admin-alerts.php` from a scheduled job. Inspect pending items without sending using `php tools/send-admin-alerts.php --dry-run`.

The application also requires PHP `mysqli` and a MySQL-compatible database for integration testing; those are not available in the current local CLI environment.
