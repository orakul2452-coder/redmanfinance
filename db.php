<?php
mysqli_report(MYSQLI_REPORT_OFF);

function redman_db_ssl_ca_path()
{
    $candidates = array_filter([
        getenv('DB_SSL_CA') ?: null,
        getenv('MYSQL_SSL_CA') ?: null,
        '/etc/secrets/aiven-ca.pem',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/cert.pem',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ], static function ($value) {
        return is_string($value) && $value !== '';
    });

    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            return $candidate;
        }
    }

    return '';
}

define('DB_SERVER', getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306));
define('DB_USERNAME', getenv('DB_USER') ?: getenv('MYSQL_USER') ?: getenv('DB_USERNAME') ?: '');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: '');
define('DB_SSL_CA', redman_db_ssl_ca_path());

if (DB_SERVER === '' || DB_PORT < 1 || DB_USERNAME === '' || DB_PASSWORD === '' || DB_NAME === '') {
    $errorMessage = 'Database configuration incomplete: set DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, and DB_NAME.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $errorMessage . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit($errorMessage);
}

$link = mysqli_init();
$connectFlags = 0;

if (DB_SSL_CA !== '') {
    mysqli_options($link, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    mysqli_ssl_set($link, null, null, DB_SSL_CA, null, null);
    $connectFlags = MYSQLI_CLIENT_SSL;
}

if (!@mysqli_real_connect($link, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT, null, $connectFlags)) {
    $errorMessage = 'ERROR: Could not connect to the database: ' . mysqli_connect_error();
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $errorMessage . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    exit($errorMessage);
}
?>