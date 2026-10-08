<?php
session_start();
include "../db.php";
include "../config.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$date = date('Y-m-d H:i:s');

// Authentication and data fetching
if(isset($_SESSION['email'])){
    $email = $link->real_escape_string($_SESSION['email']);
    $sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($link, $sql1);
    if(mysqli_num_rows($result) > 0){
        $row1 = mysqli_fetch_assoc($result);
        $ubalance = round($row1['walletbalance'],2);
        $uprofit = round($row1['profit'],2);
        $username = $row1['username'];
        $referred = $row1['referred'];
        $refcode = $row1['refcode'];
    } else {
        header("location: ../trade/login.php");
        exit;
    }
} else {
    header('location: ../trade/login.php');
    die();
}

$withdrawnProfit = 0;
$withdrawals = $link->prepare("SELECT COALESCE(SUM(usd), 0) FROM btc WHERE email = ? AND type = 'Withdrawal' AND status IN ('pending', 'approved')");
if ($withdrawals) {
    $withdrawals->bind_param('s', $email);
    if ($withdrawals->execute()) {
        $withdrawals->bind_result($withdrawnProfit);
        $withdrawals->fetch();
    }
    $withdrawals->close();
}
$withdrawable = max(0, min($ubalance, $uprofit - (float)$withdrawnProfit));

// Form processing
if(isset($_POST['send'])) {
    $amountInput = trim($_POST['amount'] ?? '');
    if ($amountInput === '' || !is_numeric($amountInput)) {
        $msg = "Amount is required";
    } else {
        $amount = (float)$amountInput;
    }
    
    if (empty($_POST["currency"])) {
        $msg = "Currency is required";
    } else {
        $mode = $link->real_escape_string($_POST["currency"]);
    }
    
    if (empty($_POST["wallet"])) {
        $msg = "Wallet address is required.";
    } else {
        $wallet = $link->real_escape_string($_POST["wallet"]);
    }

    if ($msg === '' && $amount < 7) {
        $msg = "Minimum withdrawal is $7!";
    }
    if ($msg === '') {
        $tnx = uniqid('tnx');
        $link->begin_transaction();
        $lockedBalance = 0;
        $lockedProfit = 0;
        $lock = $link->prepare("SELECT walletbalance, profit FROM users WHERE email = ? FOR UPDATE");
        $locked = $lock && $lock->bind_param('s', $email) && $lock->execute() && $lock->bind_result($lockedBalance, $lockedProfit) && $lock->fetch();
        if ($lock) {
            $lock->close();
        }

        $lockedWithdrawnProfit = 0;
        $lockedWithdrawals = $link->prepare("SELECT COALESCE(SUM(usd), 0) FROM btc WHERE email = ? AND type = 'Withdrawal' AND status IN ('pending', 'approved')");
        $withdrawalsLoaded = $locked && $lockedWithdrawals && $lockedWithdrawals->bind_param('s', $email) && $lockedWithdrawals->execute() && $lockedWithdrawals->bind_result($lockedWithdrawnProfit) && $lockedWithdrawals->fetch();
        if ($lockedWithdrawals) {
            $lockedWithdrawals->close();
        }

        $currentWithdrawable = max(0, min((float)$lockedBalance, (float)$lockedProfit - (float)$lockedWithdrawnProfit));
        if (!$withdrawalsLoaded || $amount > $currentWithdrawable) {
            $link->rollback();
            $msg = $withdrawalsLoaded ? "Insufficient Fund!" : "Unable to verify withdrawal balance. Please try again.";
        }

        $update = $link->prepare("UPDATE users SET walletbalance = walletbalance - ? WHERE email = ? AND walletbalance >= ?");
        $insert = $link->prepare("INSERT INTO btc (plan, cointype, allamount, mode, usd, type, email, status, account, comment, tnxid, refcode, referred, date) VALUES ('', '', '', ?, ?, 'Withdrawal', ?, 'pending', ?, '', ?, ?, ?, ?)");
        $updated = $msg === '' && $update && $update->bind_param('dsd', $amount, $email, $amount) && $update->execute() && $update->affected_rows === 1;
        $recorded = $updated && $insert && $insert->bind_param('sdssssss', $mode, $amount, $email, $wallet, $tnx, $refcode, $referred, $date) && $insert->execute();

        if ($recorded && $link->commit()) {
                // Send confirmation email
                require 'Exception.php';
                require 'PHPMailer.php';
                require 'SMTP.php';
                
                $mail = new PHPMailer(true);
                
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = getenv('SMTP_HOST') ?: '';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('SMTP_USERNAME') ?: '';
                    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            
                    $mail->Port       = 465;   
                    
                    // Recipients
                    $mail->setFrom(getenv('SMTP_USERNAME') ?: '', 'Redman Finance');
                    $mail->addAddress($email);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Withdrawal Request - Redman Finance';
                    
                    // Email body with dark theme styling
                    $show = ($mode == "Perfect Money") ? "account" : "wallet address";
                    $mail->Body = '
                    <div style="background: #0D1627; color: #ffffff; padding: 20px; font-family: Arial, sans-serif;">
                        <div style="max-width: 600px; margin: 0 auto; border: 1px solid #f4d35e; border-radius: 5px;">
                            <div style="background: #1a2639; padding: 20px; text-align: center; border-bottom: 2px solid #f4d35e;">
                                <img src="https://redmanfinance.org.ng/public/redman%20finance.svg" alt="Redman Finance" style="height: 50px;">
                                <h2 style="color: #f4d35e; margin-top: 10px;">Withdrawal Request</h2>
                            </div>
                            
                            <div style="padding: 20px;">
                                <p>Hello '.$username.',</p>
                                <p>Your withdrawal request has been received and is being processed.</p>
                                
                                <div style="background: #1a2639; padding: 15px; margin: 20px 0; border-left: 3px solid #f4d35e;">
                                    <p style="margin: 0;">Amount: <strong>$'.$amount.' USD ('.$mode.')</strong></p>
                                    <p style="margin: 0;">Destination: <strong>'.$wallet.'</strong></p>
                                    <p style="margin: 0;">Transaction ID: <strong>'.$tnx.'</strong></p>
                                    <p style="margin: 0;">Status: <strong>Pending Processing</strong></p>
                                </div>
                                
                                <p>Your withdrawal will be processed within 12 hours.</p>
                                <p>If you have any questions, please contact our support team.</p>
                            </div>
                            
                            <div style="background: #1a2639; padding: 15px; text-align: center; border-top: 1px solid #f4d35e; font-size: 12px;">
                                <p>© '.date('Y').' Redman Finance. All rights reserved.</p>
                                <p>This is an automated message, please do not reply directly to this email.</p>
                            </div>
                        </div>
                    </div>';
                    
                    $mail->AltBody = "Withdrawal Request\n\nHello $username,\n\nYour withdrawal request of $amount USD ($mode) to $wallet has been received.\nTransaction ID: $tnx\nStatus: Pending Processing\n\nYour withdrawal will be processed within 12 hours.\n\nThank you,\nRedman Finance Team";
                    
                    $mail->send();
                    
                    echo "<script>
                        alert('Your withdrawal request of $".$amount." USD (".$mode.") has been submitted successfully.\\nTransaction ID: ".$tnx."\\n\\nAll withdrawals are processed within 12 hours.\\nThanks for choosing Redman Finance');
                        window.location.href='history.php';
                    </script>";
                    
                } catch (Exception $e) {
                    echo "<script>
                        alert('Your withdrawal was recorded but we couldn\\'t send a confirmation email. Please note your transaction ID: ".$tnx."');
                        window.location.href='history.php';
                    </script>";
                }
        } else {
            $link->rollback();
            if ($msg === '') {
                error_log('Withdrawal request failed: ' . $link->error . ($insert ? ' ' . $insert->error : ''));
                $msg = $updated ? "Unable to record withdrawal request. Your balance was not changed." : "Unable to process withdrawal. Your balance was not changed.";
            }
        }
    }
}

// Get user's available currencies
$sql1wth = "SELECT * FROM btc WHERE email = '$email' AND type = 'Deposit' AND status = 'approved'";
$resultwth = mysqli_query($link, $sql1wth);
$availableCurrencies = [];
while($row1wth = mysqli_fetch_assoc($resultwth)){
    $availableCurrencies[$row1wth['cointype']] = 1;
}

// Get current balance
$sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
$result = mysqli_query($link, $sql1);
if(mysqli_num_rows($result) > 0){
    $row1 = mysqli_fetch_assoc($result);
    $ubalance = round($row1['walletbalance'],2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/png" href="../public/REDMAN FINANCE.svg">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Withdrawal | Redman Finance</title>
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
        .balance-card {
            background: linear-gradient(135deg, #105BAA 0%, #1A2639 100%);
            border: none;
        }
        .form-input {
            background-color: #2a3a56;
            border: 1px solid #3a4a66;
            color: white;
        }
        .form-input:focus {
            border-color: #f4d35e;
            box-shadow: 0 0 0 2px rgba(244, 211, 94, 0.2);
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
                
                <a href="withdrawal.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md bg-primary text-darkbg">
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
                <h1 class="text-xl font-semibold text-gray-100">Withdraw Funds</h1>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="col-span-1">
                    <div class="card balance-card p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">Withdrawable Balance</h3>
                        <p class="text-gray-300 mb-4">Total Earnings Available for Withdrawal</p>
                        <h1 class="text-3xl font-bold text-primary">$<?php echo number_format($withdrawable, 2); ?></h1>
                    </div>
                </div>
                
                <div class="col-span-1 md:col-span-2">
                    <div class="card p-6">
                        <h2 class="text-xl font-bold text-primary mb-4">Withdrawal Request</h2>
                        
                        <?php if($msg != ""): ?>
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                            <p><?php echo $msg; ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-gray-300 mb-2">Amount ($)</label>
                                    <input type="number" name="amount" step="0.01" min="7" 
                                           class="w-full px-4 py-2 form-input rounded" 
                                           placeholder="Minimum $7" required>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-300 mb-2">Select Currency</label>
                                    <select name="currency" class="w-full px-4 py-2 form-input rounded" required>
                                        <?php 
                                        $sql22 = mysqli_query($link, "SELECT * FROM wallet");
                                        if(mysqli_num_rows($sql22) > 0){
                                            while ($row = mysqli_fetch_assoc($sql22)) {
                                                $namee = $row['name'];
                                                $selected = isset($availableCurrencies[$namee]) ? '' : 'disabled';
                                                echo "<option value='$namee' $selected>$namee</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-300 mb-2">Wallet/Account Address</label>
                                    <input type="text" name="wallet" 
                                           class="w-full px-4 py-2 form-input rounded" 
                                           placeholder="Enter your wallet address" required>
                                </div>
                                
                                <div class="pt-4">
                                    <button type="submit" name="send" 
                                            class="w-full py-2 px-4 bg-primary text-darkbg font-medium rounded hover:bg-yellow-600 transition"
                                            onclick="return confirm('Please ensure your wallet address is correct before submitting. Proceed?');">
                                        <i class="fas fa-paper-plane mr-2"></i> Submit Withdrawal Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="card p-6">
                <h2 class="text-xl font-bold text-primary mb-4">Withdrawal Information</h2>
                <div class="space-y-2 text-gray-300">
                    <p><i class="fas fa-info-circle text-primary mr-2"></i> Minimum withdrawal amount: $7</p>
                    <p><i class="fas fa-clock text-primary mr-2"></i> Processing time: Up to 12 hours</p>
                    <p><i class="fas fa-exclamation-triangle text-primary mr-2"></i> Ensure your wallet address is correct before submitting</p>
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
        // Mobile menu toggle
        document.querySelector('.md\\:hidden').addEventListener('click', function() {
            document.querySelector('.fixed.inset-y-0').classList.toggle('-translate-x-full');
        });
    </script>
</body>
</html>