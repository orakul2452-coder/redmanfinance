<?php


session_start();




include "../db.php";
include "../config.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;



if(isset($_SESSION['email'])){

    $email = $link->real_escape_string($_SESSION['email']);

    $sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($link, $sql1);
    if(mysqli_num_rows($result) > 0){

        $row1 = mysqli_fetch_assoc($result);
        $ubalance = round($row1['walletbalance'],2);
        $uprofit = round($row1['profit'],2);
        $refcode = $row1['refcode'];
       
        

    

    }else{
  
  
        header("location: ../login.php");
        }
}else{
    header('location: ../login.php');
    die();
}



$pdbalance = 0;
$pdprofit = 0;
$percentage = 0;
$wbtc1 = 0;


                             


$sql = "SELECT * FROM investment WHERE email='$email' ORDER BY id DESC";
$result = mysqli_query($link, $sql);
if(mysqli_num_rows($result) > 0){
    while($row = mysqli_fetch_assoc($result)){   
        $uid = $row['id'];
        $pdate = $row['pdate'];
        $duration = (int)$row['duration'];
        $increase = (float)$row['increase'];
        $usd = (float)$row['usd'];
        $activate = (int)$row['activate'];
        $lprofit = (float)$row['lprofit'];
        
        $start_ts = strtotime($pdate);
        if ($start_ts === false || $start_ts <= 0) {
            $start_ts = time();
        }
        $end_ts = $start_ts + ($duration * 86400);
        $now = time();
        
        if($activate == 1){
            if($now >= $end_ts){
                $final_profit = round(($increase / 100) * $usd * $duration, 2);
                $incremental_profit = max(0, $final_profit - $lprofit);
                $ppr = $incremental_profit + $usd;
                
                mysqli_query($link, "UPDATE users SET walletbalance = walletbalance + $ppr, profit = profit + $incremental_profit WHERE email='$email'");
                mysqli_query($link, "UPDATE investment SET activate = '0', profit = '$final_profit', lprofit = '$final_profit', payday = '".date('Y/m/d')."' WHERE id = '$uid'");
            } else {
                $elapsed_secs = max(0, $now - $start_ts);
                $elapsed_days = min((float)$duration, $elapsed_secs / 86400);
                $current_profit = round(($increase / 100) * $usd * $elapsed_days, 2);
                
                $incremental_profit = max(0, $current_profit - $lprofit);
                if($incremental_profit >= 0.01){
                    mysqli_query($link, "UPDATE users SET walletbalance = walletbalance + $incremental_profit, profit = profit + $incremental_profit WHERE email='$email'");
                    mysqli_query($link, "UPDATE investment SET profit = '$current_profit', lprofit = '$current_profit', payday = '".date('Y/m/d')."' WHERE id = '$uid'");
                }
            }
        }
    }
}

	


$sql211= "SELECT SUM(usd) as total_value FROM btc WHERE type = 'Withdrawal' AND email= '$email' and status= 'approved'";
$result211 = mysqli_query($link,$sql211);
$row11 = mysqli_fetch_assoc($result211);
if($row11['total_value'] != ""){
$wbtc1 = round($row11['total_value'],2);
}else{
$wbtc1 = 0;
}

 $sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($link, $sql1);
    if(mysqli_num_rows($result) > 0){

        $row1 = mysqli_fetch_assoc($result);
        $ubalance = round($row1['walletbalance'],2);
        $uprofit = round($row1['profit'],2);
    }




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/png" href="../public/REDMAN FINANCE.svg">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Dashboard | Redman Finance</title>
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
        .tradingview-widget-container {
            border-radius: 0.5rem;
            overflow: hidden;
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
                <a href="./" class="flex items-center px-4 py-3 text-sm font-medium rounded-md bg-primary text-darkbg">
                    <i class="fas fa-chart-line mr-3"></i>
                    Dashboard
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
                
                <a href="withdrawal.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-money-bill-wave mr-3"></i>
                    Withdrawal
                </a>
                
                <a href="history.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-history mr-3"></i>
                    History
                </a>
                
                <a href="profile.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-user mr-3"></i>
                    User Profile
                </a>
                
                <a href="password.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-key mr-3"></i>
                    Change Password
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
                <h1 class="text-xl font-semibold text-gray-100">Dashboard</h1>
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
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card p-6">
                    <div class="flex flex-col">
                        <h3 class="text-sm font-medium text-gray-400">Wallet Balance</h3>
                        <p class="text-xs text-gray-500 mb-2">Current available balance</p>
                        <h2 class="text-2xl font-bold text-primary">$<?php echo $ubalance; ?></h2>
                    </div>
                </div>
                
                <div class="card p-6">
                    <div class="flex flex-col">
                        <h3 class="text-sm font-medium text-gray-400">Total Earnings</h3>
                        <p class="text-xs text-gray-500 mb-2">All time profit</p>
                        <h2 class="text-2xl font-bold text-white">$<?php echo $uprofit; ?></h2>
                    </div>
                </div>
                
                <div class="card p-6">
                    <div class="flex flex-col">
                        <h3 class="text-sm font-medium text-gray-400">Total Withdrawn</h3>
                        <p class="text-xs text-gray-500 mb-2">All time withdrawals</p>
                        <h2 class="text-2xl font-bold text-primary">$<?php echo $wbtc1; ?></h2>
                    </div>
                </div>
            </div>

            <!-- Referral Section -->
            <!-- <div class="card p-6 mb-8">
                <h3 class="text-lg font-medium text-gray-100 mb-4">Referral Link</h3>
                <div class="flex flex-col md:flex-row gap-4">
                    <input type="text" class="flex-1 px-4 py-2 bg-gray-800 border border-gray-700 rounded text-white" 
                           value="#=<?php echo $refcode; ?>" id="myInputs" readonly>
                    <button onclick="myFunctions()" 
                            class="px-4 py-2 bg-primary text-darkbg font-medium rounded hover:bg-yellow-600 transition">
                        Copy Referral Link
                    </button>
                </div>
            </div> -->

            <!-- TradingView Widget -->
            <div class="card p-0 overflow-hidden">
                <div class="tradingview-widget-container">
                    <div id="tradingview_f263f" class="h-[610px]"></div>
                    <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
                    <script type="text/javascript">
                    new TradingView.widget({
                        "width": "100%",
                        "height": "610",
                        "symbol": "FX:EURUSD",
                        "timezone": "Etc/UTC",
                        "theme": "Dark",
                        "style": "1",
                        "locale": "en",
                        "toolbar_bg": "#1A2639",
                        "enable_publishing": false,
                        "withdateranges": true,
                        "range": "all",
                        "allow_symbol_change": true,
                        "save_image": false,
                        "details": true,
                        "hotlist": true,
                        "calendar": true,
                        "news": ["stocktwits", "headlines"],
                        "studies": ["BB@tv-basicstudies", "MACD@tv-basicstudies", "MF@tv-basicstudies"],
                        "container_id": "tradingview_f263f"
                    });
                    </script>
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
        function myFunctions() {
            var copyText = document.getElementById("myInputs");
            copyText.select();
            document.execCommand("copy");
            alert("Copied referral link: " + copyText.value);
        }
        
        // Mobile menu toggle
        document.querySelector('.md\\:hidden').addEventListener('click', function() {
            document.querySelector('.fixed.inset-y-0').classList.toggle('-translate-x-full');
        });
    </script>
</body>
</html>