<?php

require_once __DIR__ . '/../email_notifications.php';

class SmtpFallbackTestMailer extends PHPMailer\PHPMailer\PHPMailer
{
    public static array $attempts = [];

    public function send()
    {
        self::$attempts[] = [$this->Port, $this->SMTPSecure];
        if ($this->Port === 587) {
            throw new PHPMailer\PHPMailer\Exception('Simulated primary SMTP connection failure.');
        }

        return true;
    }
}

function check_smtp_fallback(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

putenv('SMTP_HOST=smtp.example.test');
putenv('SMTP_USERNAME=sender@example.test');
putenv('SMTP_PASSWORD=test-password');
putenv('SMTP_PORT=587');
putenv('SMTP_PORT_FALLBACK=465');

redman_email_send_with_smtp_fallback(
    static fn() => new SmtpFallbackTestMailer(true),
    'SMTP fallback test'
);

check_smtp_fallback(
    SmtpFallbackTestMailer::$attempts === [
        [587, 'tls'],
        [465, 'ssl'],
    ],
    'failed STARTTLS primary retries using SSL fallback'
);

putenv('SMTP_PORT=465');
putenv('SMTP_PORT_FALLBACK=465');
$factoryCalled = false;
try {
    redman_email_send_with_smtp_fallback(static function () use (&$factoryCalled) {
        $factoryCalled = true;
        return new SmtpFallbackTestMailer(true);
    }, 'Invalid SMTP configuration test');
    check_smtp_fallback(false, 'duplicate SMTP ports are rejected');
} catch (InvalidArgumentException $error) {
    check_smtp_fallback(!$factoryCalled, 'duplicate SMTP ports are rejected before sending');
}
