<?php

if (!defined('REDMAN_SUPPORT_PHONE')) {
  define('REDMAN_SUPPORT_PHONE', '+18167488978');
}
if (!defined('REDMAN_SUPPORT_PHONE_LABEL')) {
  define('REDMAN_SUPPORT_PHONE_LABEL', '+1 (816) 748-8978');
}

if (!function_exists('redman_investment_duration_options')) {
  function redman_investment_duration_options()
  {
    return [
      7 => '7 days',
      30 => '1 month (30 days)',
      90 => '3 months (90 days)',
      180 => '6 months (180 days)',
      365 => '1 year (365 days)',
    ];
  }
}

if (!function_exists('redman_is_valid_investment_duration')) {
  function redman_is_valid_investment_duration($duration)
  {
    $validatedDuration = filter_var($duration, FILTER_VALIDATE_INT);
    return $validatedDuration !== false && array_key_exists($validatedDuration, redman_investment_duration_options());
  }
}

@session_start();

include_once __DIR__ . '/db.php';

$configDefaults = [
  'currency' => '',
  'name' => '',
  'logo' => '',
  'emaila' => '',
  'phone' => '',
  'address' => '',
  'title' => '',
  'branch' => '',
  'bankurl' => '',
  'wl' => '',
  'rb' => '',
  'ids' => '',
  'init' => '',
  'act' => '',
  'cy' => '',
  'pre' => '',
  'jso' => '',
  'api' => '',
  'eapi' => '',
];
extract($configDefaults, EXTR_SKIP);

$appBaseUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://redmanfinance.onrender.com', '/');
$appLogoUrl = $appBaseUrl . '/public/REDMAN%20FINANCE.svg';

$settingsResult = mysqli_query($link, 'SELECT * FROM settings');
if ($settingsResult instanceof mysqli_result) {
  $row = mysqli_fetch_assoc($settingsResult);
  if ($row !== null) {
    $currency = $row['currency'] ?? '';
    $name = $row['bname'] ?? '';
    $logo = $row['logo'] ?? '';
    $emaila = $row['email'] ?? '';
    $phone = $row['phone'] ?? '';
    $address = $row['baddress'] ?? '';
    $title = $row['title'] ?? '';
    $branch = $row['branch'] ?? '';
    $bankurl = $row['sname'] ?? '';
    $wl = $row['wl'] ?? '';
    $rb = $row['rb'] ?? '';
    $ids = $row['id'] ?? '';
    $init = $row['hea'] ?? '';
    $act = $row['act'] ?? '';
    $cy = $row['cy'] ?? '';
    $pre = $row['inert'] ?? '';
    $jso = $row['jso'] ?? '';
  }
}

?>