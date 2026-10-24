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
putenv('MAKE_EMAIL_WEBHOOK_URL=https://hooks.example.test/email');

$makeMailer = new PHPMailer\PHPMailer\PHPMailer(true);
$makeMailer->setFrom('sender@example.test', 'Redman Finance');
$makeMailer->addAddress('customer@example.test');
$makeMailer->Subject = 'Verify your email';
$makeMailer->Body = '<p>Verification message</p>';
$makeMailer->AltBody = 'Verification message';
$makeRequest = null;
redman_make_send_email($makeMailer, 'Make webhook test', static function (string $url, string $body) use (&$makeRequest): array {
    $makeRequest = [$url, json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
    return [200, '{"delivered":true}'];
});
check_smtp_fallback(
    $makeRequest === ['https://hooks.example.test/email', [
        'schema_version' => 1,
        'type' => 'email',
        'to' => 'customer@example.test',
        'from' => 'sender@example.test',
        'from_name' => 'Redman Finance',
        'subject' => 'Verify your email',
        'html' => '<p>Verification message</p>',
        'text' => 'Verification message',
    ]],
    'Make receives a normalized email payload'
);

redman_make_send_email($makeMailer, 'Make webhook test', static function (string $url, string $body) use (&$makeRequest): array {
    $makeRequest = [$url, json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
    return [200, '{"accepted":true}'];
});
check_smtp_fallback(
    $makeRequest === ['https://hooks.example.test/email', [
        'schema_version' => 1,
        'type' => 'email',
        'to' => 'customer@example.test',
        'from' => 'sender@example.test',
        'from_name' => 'Redman Finance',
        'subject' => 'Verify your email',
        'html' => '<p>Verification message</p>',
        'text' => 'Verification message',
    ]],
    'Make accepts explicit accepted confirmations as successful delivery'
);

putenv('MAKE_EMAIL_WEBHOOK_URL');
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
