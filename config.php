<?php

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