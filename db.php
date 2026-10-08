<?php
mysqli_report(MYSQLI_REPORT_OFF);

define('DB_SERVER', getenv('DB_HOST') ?: '');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 0));
define('DB_USERNAME', getenv('DB_USER') ?: '');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');

if (DB_SERVER === '' || DB_PORT < 1 || DB_USERNAME === '' || DB_PASSWORD === '' || DB_NAME === '' || DB_SSL_CA === '' || !is_readable(DB_SSL_CA)) {
    $errorMessage = 'Database configuration incomplete: set DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, and a readable DB_SSL_CA certificate.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $errorMessage . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit($errorMessage);
}

$link = mysqli_init();
mysqli_options($link, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
mysqli_ssl_set($link, null, null, DB_SSL_CA, null, null);

if (!@mysqli_real_connect($link, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT, null, MYSQLI_CLIENT_SSL)) {
    $errorMessage = 'ERROR: Could not connect to the database: ' . mysqli_connect_error();
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $errorMessage . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit($errorMessage);
}
?>