<?php
session_start();
include "../db.php";
include "../config.php";
require_once "../risk.php";
require_once "../email_notifications.php";
require_once "../referral_rewards.php";
require_once "../admin_notifications.php";

$msg = "";


// Authentication and data fetching logic
if(isset($_SESSION['email'])){
    $email = $link->real_escape_string($_SESSION['email']);
    $sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($link, $sql1);
    if(mysqli_num_rows($result) > 0){
        $row1 = mysqli_fetch_assoc($result);
        $ubalance = round($row1['walletbalance'],2);
        $uprofit = round($row1['profit'],2);
        $refcode = (string)($row1['refcode'] ?? '');
        $referred = $row1['referred'];
        $username = $row1['username'];
    } else {
        header("location: ../login.php");
    }
} else {
    header('location: ../login.php');
    die();
}

$row1 = redman_risk_refresh_hold($link, $email) ?: $row1;
$accountOnHold = !empty($row1['is_on_hold']);

$pname = "";
$amount = "";
$currency = "";
$address = "";

// Form processing logic
if(isset($_POST['submit'])){
    $pname = $link->real_escape_string($_POST["pname"]);
    $amount = $link->real_escape_string($_POST["amount"]);
    $currency = $link->real_escape_string($_POST["currency"]);
    $duration = $_POST['duration'] ?? '';
    if (!redman_is_valid_investment_duration($duration)) {
        header("location: packages.php");
        exit();
    }
    $duration = (int)$duration;

    $sql12 = "SELECT * FROM package1 WHERE pname = '$pname' LIMIT 1";
    $result2 = mysqli_query($link, $sql12);
    if(mysqli_num_rows($result2) > 0){
        $row12 = mysqli_fetch_assoc($result2);
        $uincrease = $row12['increase'];
        $uduration = $row12['duration'];
        $ufrom = $row12['froms'];
        $uto = $row12['tos'];
    } else {
        echo "<script>alert('The selected investment plan is no longer available.'); window.location.href='packages.php';</script>";
        exit();
    }
    if ((float)$amount < (float)$ufrom || (float)$amount > (float)$uto || (float)$amount <= 0) {
        echo "<script>alert('The selected investment amount is outside this plan range.'); window.location.href='packages.php';</script>";
        exit();
    }
    $promoPreview = redman_referral_promo_preview($link, (int)$row1['id'], (float)$amount);
    $paymentAmount = max(0, (float)$amount - (float)$promoPreview['discount']);
    
    if ($currency == "Account Wallet Balance") {
        $link->begin_transaction();
        $userLock = $link->prepare('SELECT id, walletbalance, is_on_hold FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
        $userLock->bind_param('s', $email);
        $userLock->execute();
        $lockedUser = $userLock->get_result()->fetch_assoc();
        $userLock->close();
        if (!$lockedUser || (int)$lockedUser['is_on_hold'] === 1) {
            $link->rollback();
            echo "<script>alert('Wallet-funded investments are unavailable while your account is under risk review.'); window.location.href='packages.php';</script>";
            exit();
        }

        $grossAmount = (float)$amount;
        $promo = redman_referral_promo_reserve($link, (int)$lockedUser['id'], $grossAmount);
        $amountValue = max(0, $grossAmount - (float)$promo['discount']);
        $avail_bal = (float)$lockedUser['walletbalance'];
        if ($amountValue <= 0 || $avail_bal < $amountValue) {
            $link->rollback();
            echo "<script>alert('Insufficient wallet balance! Amount due: $".number_format($amountValue, 2).". Your available balance is $".number_format($avail_bal, 2)."'); window.location.href='packages.php';</script>";
            exit();
        }

        $cdate = date('Y-m-d H:i:s');
        $cpayday = date('Y/m/d');
        $increaseValue = (float)$uincrease;
        $durationValue = $duration;
        $fromValue = (float)$ufrom;
        $userId = (int)$lockedUser['id'];
        $insertInvestment = $link->prepare("INSERT INTO investment (email, pname, increase, bonus, duration, pdate, froms, activate, usd, profit, payday, lprofit, status) VALUES (?, ?, ?, 0, ?, ?, ?, 1, ?, 0, ?, 0, '')");
        $inserted = $insertInvestment && $insertInvestment->bind_param('ssdisdds', $email, $pname, $increaseValue, $durationValue, $cdate, $fromValue, $amountValue, $cpayday) && $insertInvestment->execute();
        $investmentId = $inserted ? (int)$insertInvestment->insert_id : 0;
        if ($insertInvestment) {
            $insertInvestment->close();
        }
        $debit = $link->prepare('UPDATE users SET walletbalance = walletbalance - ? WHERE id = ? AND is_on_hold = 0 AND walletbalance >= ?');
        $debited = $inserted && $debit && $debit->bind_param('did', $amountValue, $userId, $amountValue) && $debit->execute() && $debit->affected_rows === 1;
        if ($debit) {
            $debit->close();
        }
        $referralReward = $debited ? redman_referral_promo_finalize($link, $userId, $investmentId) : null;
        $notificationIds = [];
        if ($debited) {
            try {
                $notificationIds[] = redman_email_enqueue($link, 'investment-activated:' . $investmentId, $email, 'investment_activated', [
                    'name' => $username,
                    'amount' => $amountValue,
                    'reference' => (string)$investmentId,
                    'detail' => 'Investment activated for ' . redman_investment_duration_options()[$durationValue] . ($promo['discount'] > 0 ? '; $500 referral promotion applied to the amount paid.' : '.'),
                ]);
                if ($referralReward) {
                    $referrer = $referralReward['referrer'];
                    $notificationIds[] = redman_email_enqueue($link, 'referral-cashback-paid:investment:' . $investmentId, $referrer['email'], 'referral_cashback_paid', [
                        'name' => trim((string)$referrer['fname'] . ' ' . (string)$referrer['lname']) ?: $referrer['username'],
                        'amount' => $referralReward['amount'],
                        'reference' => (string)$investmentId,
                    ]);
                }
            } catch (Throwable $mailError) {
                error_log('Investment promotion notification queue failed for investment ' . $investmentId);
            }
        }
        if ($debited && $link->commit()) {
            foreach ($notificationIds as $notificationId) {
                redman_email_dispatch($link, $notificationId);
            }
            $promoMessage = $promo['discount'] > 0 ? ' A $500 referral promotion was applied; the direct referrer received $500 cashback.' : '';
            echo "<script>alert('Investment of $".number_format($amountValue, 2)." in ".$pname." activated successfully from your wallet balance!".addslashes($promoMessage)."'); window.location.href='mypackages.php';</script>";
            exit();
        }
        $link->rollback();
        $err = addslashes($link->error ?: 'Database operation failed');
        echo "<script>alert('Failed to activate investment: ".$err."'); window.location.href='packages.php';</script>";
        exit();
    }

    $sql121 = "SELECT * FROM wallet WHERE name = '$currency' LIMIT 1";
    $result21 = mysqli_query($link, $sql121);
    if(mysqli_num_rows($result21) > 0){
        $row121 = mysqli_fetch_assoc($result21);
        $address = $row121['address'];
    } else {
        echo "<script> 
            alert('Invalid currency selected!');
            window.location.href='packages.php';
        </script>";
        exit();
    }

    if($amount < $ufrom || $amount > $uto){
        echo "<script> 
            alert('Minimum amount for the selected plan is $".$ufrom." and maximum amount is $".$uto."');
            window.location.href='packages.php';
        </script>";
        exit();
    }
} elseif(isset($_POST['send'])){
    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $planName = trim((string)($_POST['pname'] ?? ''));
    $currencyName = trim((string)($_POST['currency'] ?? ''));
    $durationRaw = $_POST['duration'] ?? '';
    if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $amountRaw) || (float)$amountRaw <= 0 || $planName === '' || $currencyName === '' || !redman_is_valid_investment_duration($durationRaw)) {
        echo '<script>alert(' . json_encode('Enter a valid amount, plan, and payment currency.') . ');window.location.href="packages.php";</script>';
        exit();
    }

    $grossAmount = (float)$amountRaw;
    $durationValue = (int)$durationRaw;
    $packageLookup = $link->prepare('SELECT froms, tos FROM package1 WHERE pname = ? LIMIT 1');
    $packageLookup->bind_param('s', $planName);
    $packageLookup->execute();
    $selectedPackage = $packageLookup->get_result()->fetch_assoc();
    $packageLookup->close();
    if (!$selectedPackage || $grossAmount < (float)$selectedPackage['froms'] || $grossAmount > (float)$selectedPackage['tos']) {
        echo '<script>alert(' . json_encode('The selected plan or amount is no longer valid. Please select the plan again.') . ');window.location.href="packages.php";</script>';
        exit();
    }
    $link->begin_transaction();
    try {
        $promo = redman_referral_promo_reserve($link, (int)$row1['id'], $grossAmount);
        $amountValue = max(0, $grossAmount - (float)$promo['discount']);
        if ($amountValue <= 0) {
            throw new RuntimeException('The payable amount is invalid.');
        }
    } catch (Throwable $promoError) {
        $link->rollback();
        echo '<script>alert(' . json_encode('Unable to reserve the referral promotion. Please try again.') . ');window.location.href="packages.php";</script>';
        exit();
    }
    $transactionReference = 'tnx' . bin2hex(random_bytes(12));
    $transactionType = 'Deposit';
    $transactionStatus = 'pending';
    $allAmount = '';
    $comment = '';
    $refcode = (string)($row1['refcode'] ?? '');
    $referred = (string)($row1['referred'] ?? '');
    $insert = $link->prepare('INSERT INTO btc (plan, account, allamount, mode, usd, cointype, email, status, tnxid, type, comment, refcode, referred, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->bind_param('ssssdssssssssi', $planName, $planName, $allAmount, $currencyName, $amountValue, $currencyName, $email, $transactionStatus, $transactionReference, $transactionType, $comment, $refcode, $referred, $durationValue);
    if (!$insert->execute()) {
        error_log('Investment deposit insert failed for reference ' . $transactionReference . ': ' . $insert->error . ' [' . $insert->errno . ']');
        $insert->close();
        $link->rollback();
        echo '<script>alert(' . json_encode('Unable to record your deposit request. Please try again.') . ');window.location.href="packages.php";</script>';
        exit();
    }
    $depositId = (int)$insert->insert_id;
    $insert->close();
    if ($promo['discount'] > 0 && !redman_referral_promo_bind_deposit($link, (int)$row1['id'], $depositId)) {
        $link->rollback();
        echo '<script>alert(' . json_encode('Unable to attach the referral promotion to this deposit. Please try again.') . ');window.location.href="packages.php";</script>';
        exit();
    }
    try {
        $adminAlertId = redman_admin_alert_enqueue($link, 'investment-deposit-submitted:' . $depositId, 'investment_deposit_submitted', [
            'customer_email' => $email,
            'customer_name' => $username,
            'reference' => $transactionReference,
            'amount_usd' => $amountValue,
            'plan_amount_usd' => $grossAmount,
            'discount_usd' => (float)$promo['discount'],
            'plan' => $planName,
            'duration_days' => $durationValue,
            'payment_method' => $currencyName,
            'admin_path' => '/admin/c2wad/deposit.php',
        ]);
    } catch (Throwable $alertError) {
        error_log('Unable to queue admin investment-deposit alert for reference ' . $transactionReference);
        $adminAlertId = 0;
    }
    if (!$link->commit()) {
        error_log('Investment deposit transaction commit failed for reference ' . $transactionReference . ': ' . $link->error . ' [' . $link->errno . ']');
        $link->rollback();
        echo '<script>alert(' . json_encode('Unable to record your deposit request. Please try again.') . ');window.location.href="packages.php";</script>';
        exit();
    }

    try {
        if ($adminAlertId > 0) {
            redman_admin_alert_dispatch($link, $adminAlertId);
        }
        $pendingNotificationId = redman_email_enqueue($link, 'deposit-pending:' . $transactionReference, $email, 'deposit_pending', [
            'name' => $username,
            'amount' => $amountValue,
            'reference' => $transactionReference,
            'detail' => 'Selected investment term: ' . redman_investment_duration_options()[$durationValue] . '. Plan amount: $' . number_format($grossAmount, 2) . ($promo['discount'] > 0 ? '; $500 referral promotion applied. Amount due: $' . number_format($amountValue, 2) : '') . '. Payment method: ' . $currencyName,
        ]);
        redman_email_dispatch($link, $pendingNotificationId);
    } catch (Throwable $mailError) {
        error_log('Deposit-pending notification failed for reference ' . $transactionReference);
    }
    echo '<script>alert(' . json_encode('Your deposit request was recorded and is pending review. Reference: ' . $transactionReference) . ');window.location.href="mypackages.php";</script>';
    exit();
} else {
    header("location: packages.php");
    exit();
}

function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/png" href="../public/REDMAN FINANCE.svg">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Payment Process | Redman Finance</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
    <meta name="viewport" content="width=device-width" />

    <!-- Tailwind CSS CDN with custom colors -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f4d35e',
                        darkbg: '#0D1627',
                        cardbg: '#1A2639',
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #0D1627;
            color: #ffffff;
        }
        .sidebar {
            background-color: #1A2639;
        }
        .nav li a {
            color: #ffffff;
        }
        .nav li a:hover {
            background-color: rgba(244, 211, 94, 0.1);
        }
        .nav li.active a {
            background-color: #f4d35e;
            color: #0D1627;
        }
        .navbar {
            background-color: #1A2639 !important;
        }
        .card {
            background-color: #1A2639;
            border: 1px solid #2a3a56;
            border-radius: 0.5rem;
        }
        .footer {
            background-color: #1A2639;
            color: #ffffff;
        }
        .payment-info {
            background-color: #1A2639;
            border-left: 3px solid #f4d35e;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- Sidebar -->
    <div class="w-64 fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition duration-200 ease-in-out z-50 bg-[#0D1627]">
        <div class="h-full flex flex-col">
            <div class="p-4 flex items-center justify-center">
                <a href="../" class="flex items-center space-x-3">
                    <img src="../public/REDMAN FINANCE.svg" alt="Redman Finance Logo" class="h-10">
                </a>
            </div>
            
            <nav class="flex-1 px-2 space-y-1">
                <a href="./" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-chart-line mr-3"></i>
                    Dashboard
                </a>
                
                <a href="profile.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-user mr-3"></i>
                    User Profile
                </a>
                
                <a href="packages.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-boxes mr-3"></i>
                    Investment Plans
                </a>

                <a href="wallet.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-wallet mr-3"></i>
                    Add Funds
                </a>
                
                <a href="mypackages.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-wallet mr-3"></i>
                    My Investments
                </a>
                
                <a href="password.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-key mr-3"></i>
                    Change Password
                </a>
                
                <a href="withdrawal.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-money-bill-wave mr-3"></i>
                    Withdrawal
                </a>
                
                <a href="logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 md:ml-64">
        <!-- Top Navigation -->
        <header class="bg-cardbg shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-xl font-semibold text-gray-100">Deposit Funds</h1>
                <div class="flex items-center space-x-4">
                    <!-- Mobile menu button -->
                    <button class="md:hidden text-gray-400 hover:text-white focus:outline-none">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <div class="card p-6 mb-6">
                <h2 class="text-xl font-bold text-primary mb-4 text-center"><?php echo $pname; ?> Payment Process</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <?php 
                        $addr = ($currency == "Perfect Money") ? "Account ID" : "Address";
                        $crypt = ($currency == "Perfect Money") ? "amount" : "crypto";
                        ?>
                        
                        <div class="payment-info mb-6">
                            <h3 class="text-lg font-semibold text-primary mb-2">Payment Instructions</h3>
                            <p class="mb-4">Make payment of <span class="font-bold text-primary">$<?php echo number_format($paymentAmount, 2); ?></span> to the below <?php echo $currency; ?> <?php echo $addr; ?>:</p>
                            <?php if (!empty($promoPreview['eligible'])): ?>
                                <p class="mb-4 text-sm text-green-300">Your $500 referral promotion is applied to this qualifying investment. Investment principal and earnings are based on the amount paid.</p>
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <input type="text" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded text-white" 
                                       value="<?php echo $address; ?>" id="paymentAddress" readonly>
                                <button onclick="copyToClipboard('paymentAddress', '<?php echo $addr; ?>')" 
                                        class="mt-2 w-full py-2 px-4 bg-primary text-darkbg font-medium rounded hover:bg-yellow-600 transition">
                                    Copy <?php echo $currency." ".$addr; ?>
                                </button>
                            </div>
                            
                            <!-- <?php if($currency != "Perfect Money"): ?>
                            <div class="text-center">
                                <p class="mb-2">QR Code</p>
                                <img src="https://chart.googleapis.com/chart?chs=225x225&chld=L|2&cht=qr&chl=<?php echo $address; ?>" 
                                     alt="<?php echo $currency; ?> QR Code" class="mx-auto">
                            </div>
                            <?php endif; ?> -->
                        </div>
                    </div>
                    
                    <div>
                        <form action="process3.php" method="post">
                            <input type="hidden" value="<?php echo $pname; ?>" name="pname">
                            <input type="hidden" value="<?php echo $amount; ?>" name="amount">
                            <input type="hidden" value="<?php echo $currency; ?>" name="currency">
                            <input type="hidden" value="<?php echo $duration; ?>" name="duration">
                            
                            <div class="payment-info">
                                <h3 class="text-lg font-semibold text-primary mb-2">Confirmation</h3>
                                <p class="mb-4">Send the <?php echo $crypt; ?> to the copied <?php echo $addr; ?>. Click the "I've Deposited" button only after sending the funds.</p>
                                <p class="text-sm text-gray-400 mb-4">Note: Your deposit will be approved after confirmation by our team.</p>
                                
                                <button type="submit" name="send" 
                                        class="w-full py-2 px-4 bg-primary text-darkbg font-medium rounded hover:bg-yellow-600 transition">
                                    <i class="fas fa-check-circle mr-2"></i> I've Deposited
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-cardbg py-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-400 text-sm">
                &copy; 2013 - <?php echo date("Y"); ?> | Redman Finance All Rights Reserved
            </div>
        </footer>
    </div>

    <!-- Scripts -->
    <script>
        function copyToClipboard(elementId, label) {
            var copyText = document.getElementById(elementId);
            copyText.select();
            document.execCommand("copy");
            alert("Copied the " + label + ": " + copyText.value);
        }
        
        // Mobile menu toggle
        document.querySelector('.md\\:hidden').addEventListener('click', function() {
            document.querySelector('.fixed.inset-y-0').classList.toggle('-translate-x-full');
        });
    </script>
</body>
</html>