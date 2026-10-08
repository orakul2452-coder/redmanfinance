<?php


require 'Exception.php';
require 'PHPMailer.php';
require 'SMTP.php';
//Load Composer's autoloader
require 'vendor/autoload.php';
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();
include "../db.php";
include "../config.php";

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
        $referred = $row1['referred'];
        $username = $row1['username'];
    } else {
        header("location: ../login.php");
    }
} else {
    header('location: ../login.php');
    die();
}

$pname = "";
$amount = "";
$currency = "";
$address = "";

// Form processing logic
if(isset($_POST['submit'])){
    $pname = $link->real_escape_string($_POST["pname"]);
    $amount = $link->real_escape_string($_POST["amount"]);
    $currency = $link->real_escape_string($_POST["currency"]);

    $sql12 = "SELECT * FROM package1 WHERE pname = '$pname' LIMIT 1";
    $result2 = mysqli_query($link, $sql12);
    if(mysqli_num_rows($result2) > 0){
        $row12 = mysqli_fetch_assoc($result2);
        $uincrease = $row12['increase'];
        $uduration = $row12['duration'];
        $ufrom = $row12['froms'];
        $uto = $row12['tos'];
    }
    
    if ($currency == "Account Wallet Balance") {
        $user_q = mysqli_query($link, "SELECT walletbalance FROM users WHERE email = '$email' LIMIT 1");
        $user_row = ($user_q && mysqli_num_rows($user_q) > 0) ? mysqli_fetch_assoc($user_q) : null;
        $avail_bal = $user_row ? (float)$user_row['walletbalance'] : 0;
        
        if ($avail_bal < $amount) {
            echo "<script> 
                alert('Insufficient wallet balance! Your available balance is $".number_format($avail_bal, 2)."');
                window.location.href='packages.php';
            </script>";
            exit();
        }
        
        // Instant activation from wallet balance
        $cdate = date('Y-m-d H:i:s');
        $cpayday = date('Y/m/d');
        $ins_sql = "INSERT INTO investment (email, pname, increase, bonus, duration, pdate, froms, activate, usd, profit, payday, lprofit, status)
                    VALUES ('$email', '$pname', '$uincrease', '0', '$uduration', '$cdate', '$ufrom', '1', '$amount', '0', '$cpayday', '0', '')";
        if (mysqli_query($link, $ins_sql)) {
            mysqli_query($link, "UPDATE users SET walletbalance = walletbalance - $amount WHERE email = '$email'");
            echo "<script> 
                alert('Investment of $".number_format($amount, 2)." in ".$pname." activated successfully from your wallet balance!');
                window.location.href='mypackages.php';
            </script>";
            exit();
        } else {
            $err = addslashes(mysqli_error($link));
            echo "<script> 
                alert('Failed to activate investment: ".$err."');
                window.location.href='packages.php';
            </script>";
            exit();
        }
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
    if (empty($_POST["amount"])) {
        $msg = "Amount is required";
    } else {
        $amount = $uamount = $link->real_escape_string($_POST["amount"]);
    }
    
    if (empty($_POST["pname"])) {
        $msg = "Package name is required";
    } else {
        $pname = $upname = $link->real_escape_string($_POST["pname"]);
    }
    
    $currency = $ucurrency = $link->real_escape_string($_POST["currency"]);
    
    if(empty($msg)){
        $tnx = uniqid('tnx');
        $sql = "INSERT INTO btc (plan, account, allamount, mode, usd, cointype, email, status, tnxid, type, comment, refcode, referred)
                VALUES ('$upname', '$upname', '', '$ucurrency', '$uamount', '$ucurrency', '$email', 'pending', '$tnx', 'Deposit', '', '', '$referred')";
        
        if (mysqli_query($link, $sql)) {
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
                $mail->Subject = 'Deposit Alert - Redman Finance';
                
                $mail->Body = '
                <div style="background: #0D1627; color: #ffffff; padding: 20px; font-family: Arial, sans-serif;">
                    <div style="max-width: 600px; margin: 0 auto; border: 1px solid #f4d35e; border-radius: 5px;">
                        <div style="background: #1a2639; padding: 20px; text-align: center; border-bottom: 2px solid #f4d35e;">
                            <img src="https://redmanfinance.org.ng/public/redman%20finance.svg" alt="Redman Finance" style="height: 50px;">
                            <h2 style="color: #f4d35e; margin-top: 10px;">Deposit Alert</h2>
                        </div>
                        
                        <div style="padding: 20px;">
                            <p>Hello '.$username.',</p>
                            <p>Your deposit request has been received and is being processed.</p>
                            
                            <div style="background: #1a2639; padding: 15px; margin: 20px 0; border-left: 3px solid #f4d35e;">
                                <p style="margin: 0;">Amount: <strong>$'.$uamount.' USD ('.$ucurrency.')</strong></p>
                                <p style="margin: 0;">Transaction ID: <strong>'.$tnx.'</strong></p>
                                <p style="margin: 0;">Status: <strong>Pending Confirmation</strong></p>
                            </div>
                            
                            <p>Your investment will be activated once your deposit is confirmed by our team.</p>
                            <p>If you have any questions, please contact our support team.</p>
                        </div>
                        
                        <div style="background: #1a2639; padding: 15px; text-align: center; border-top: 1px solid #f4d35e; font-size: 12px;">
                            <p>© '.date('Y').' Redman Finance. All rights reserved.</p>
                            <p>This is an automated message, please do not reply directly to this email.</p>
                        </div>
                    </div>
                </div>';
                
                $mail->AltBody = "Deposit Alert\n\nHello $username,\n\nYour deposit of $uamount USD ($ucurrency) is currently under review.\nTransaction ID: $tnx\n\nYour investment will be activated once your deposit is confirmed.\n\nThank you,\nRedman Finance Team";
                
                $mail->send();
                
                echo "<script>
                    alert('Your deposit of ".$uamount." USD worth of ".$ucurrency." is currently under review. Your transaction ID is ".$tnx.". Your balance will be credited and your investment will be activated once your deposit is confirmed.');
                    window.location.href='mypackages.php';
                </script>";
                exit();
                
            } catch (Exception $e) {
                echo "<script>
                    alert('Your deposit was recorded. Transaction ID: ".$tnx."');
                    window.location.href='mypackages.php';
                </script>";
                exit();
            }
        } else {
            $err_msg = addslashes(mysqli_error($link));
            echo "<script>
                alert('Database Error: ".$err_msg."');
                window.location.href='packages.php';
            </script>";
            exit();
        }
    }
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
                            <p class="mb-4">Make payment of <span class="font-bold text-primary">$<?php echo $amount; ?></span> to the below <?php echo $currency; ?> <?php echo $addr; ?>:</p>
                            
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