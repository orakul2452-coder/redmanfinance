<?php
session_start();
include "../db.php";
include "../config.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if(isset($_SESSION['email'])){
    $email = $link->real_escape_string($_SESSION['email']);
    $sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($link, $sql1);
    if(mysqli_num_rows($result) > 0){
        $row1 = mysqli_fetch_assoc($result);
        $ubalance = round($row1['walletbalance'],2);
        $uprofit = round($row1['profit'],2);
    } else {
        header("location: ../login.php");
    }
} else {
    header('location: ../login.php');
    die();
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
    <title>My Investments | Redman Finance</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #2a3a56;
        }
        th {
            background-color: #1A2639;
            color: #f4d35e;
        }
        tr:hover {
            background-color: rgba(244, 211, 94, 0.05);
        }
        .status-active {
            color: #4CAF50;
        }
        .status-completed {
            color: #f4d35e;
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
                
                <a href="packages.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-boxes mr-3"></i>
                    Investment Plans
                </a>

                <a href="wallet.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-wallet mr-3"></i>
                    Add Funds
                </a>
                
                <a href="mypackages.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md bg-primary text-darkbg">
                    <i class="fas fa-wallet mr-3"></i>
                    My Investments
                </a>
                
                <a href="withdrawal.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
                    <i class="fas fa-money-bill-wave mr-3"></i>
                    Withdrawal
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
                <h1 class="text-xl font-semibold text-gray-100">My Investments</h1>
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
                <h2 class="text-xl font-bold text-primary mb-4 text-center">My Investment Portfolio</h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th>Daily Profit</th>
                                <th>Total Profit</th>
                                <th>Activation Date</th>
                                <th>End Date</th>
                                <th>Days To End</th>
                                <th>Amount Invested</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Auto-sync approved deposits into investment table if missing
                            $sync_sql = "SELECT * FROM btc WHERE email = '$email' AND status = 'approved' AND type = 'Deposit'";
                            $sync_res = mysqli_query($link, $sync_sql);
                            if ($sync_res && mysqli_num_rows($sync_res) > 0) {
                                while ($dep = mysqli_fetch_assoc($sync_res)) {
                                    $dep_usd = (float)$dep['usd'];
                                    $dep_account = $link->real_escape_string($dep['account']);
                                    $dep_date = !empty($dep['date']) ? $dep['date'] : date('Y-m-d H:i:s');
                                    
                                    $check_inv = mysqli_query($link, "SELECT id FROM investment WHERE email = '$email' AND usd = '$dep_usd' LIMIT 1");
                                    if ($check_inv && mysqli_num_rows($check_inv) == 0) {
                                        $pkg_q = mysqli_query($link, "SELECT * FROM package1 WHERE pname = '$dep_account' OR '$dep_account' LIKE CONCAT('%', pname, '%') OR pname LIKE CONCAT('%', '$dep_account', '%') LIMIT 1");
                                        $pkg_increase = 50.0;
                                        $pkg_duration = 7;
                                        $pkg_froms = $dep_usd;
                                        if ($pkg_q && mysqli_num_rows($pkg_q) > 0) {
                                            $pkg_row = mysqli_fetch_assoc($pkg_q);
                                            $pkg_increase = (float)$pkg_row['increase'];
                                            $pkg_duration = (int)$pkg_row['duration'];
                                            $pkg_froms = (float)$pkg_row['froms'];
                                        }
                                        
                                        $cpayday = date('Y/m/d');
                                        $ins_sql = "INSERT INTO investment (email, pname, increase, bonus, duration, pdate, froms, activate, usd, profit, payday, lprofit, status)
                                                    VALUES ('$email', '$dep_account', '$pkg_increase', '0', '$pkg_duration', '$dep_date', '$pkg_froms', '1', '$dep_usd', '0', '$cpayday', '0', '')";
                                        mysqli_query($link, $ins_sql);
                                    }
                                }
                            }

                            $sql = "SELECT * FROM investment WHERE email='$email' ORDER BY id DESC";
                            $result = mysqli_query($link,$sql);
                            $is_yes = 0;
                            
                            if(mysqli_num_rows($result) > 0){
                                $is_yes = 1;
                                while($row = mysqli_fetch_assoc($result)){   
                                    $uid = $row['id'];
                                    $pname = $row['pname'];
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
                                    $end_date_str = date('Y/m/d', $end_ts);
                                    $now = time();
                                    
                                    if($activate == 1){
                                        if($now >= $end_ts){
                                            // Investment completed
                                            $final_profit = round(($increase / 100) * $usd * $duration, 2);
                                            $incremental_profit = max(0, $final_profit - $lprofit);
                                            $ppr = $incremental_profit + $usd;
                                            
                                            mysqli_query($link, "UPDATE users SET walletbalance = walletbalance + $ppr, profit = profit + $incremental_profit WHERE email='$email'");
                                            mysqli_query($link, "UPDATE investment SET activate = '0', profit = '$final_profit', lprofit = '$final_profit', payday = '".date('Y/m/d')."' WHERE id = '$uid'");
                                            
                                            $current_profit = $final_profit;
                                            $days_left = 0;
                                            $sec = '<span class="status-completed"><i class="fas fa-check-circle mr-1"></i> Completed</span>';
                                        } else {
                                            // Active investment - calculate continuous real-time profit
                                            $elapsed_secs = max(0, $now - $start_ts);
                                            $elapsed_days = min((float)$duration, $elapsed_secs / 86400);
                                            $current_profit = round(($increase / 100) * $usd * $elapsed_days, 2);
                                            $days_left = max(0, (int)ceil(($end_ts - $now) / 86400));
                                            
                                            $incremental_profit = max(0, $current_profit - $lprofit);
                                            if($incremental_profit >= 0.01){
                                                mysqli_query($link, "UPDATE users SET walletbalance = walletbalance + $incremental_profit, profit = profit + $incremental_profit WHERE email='$email'");
                                                mysqli_query($link, "UPDATE investment SET profit = '$current_profit', lprofit = '$current_profit', payday = '".date('Y/m/d')."' WHERE id = '$uid'");
                                            }
                                            
                                            $sec = '<span class="status-active"><i class="fas fa-sync-alt fa-spin mr-1 text-green-400"></i> Active</span>';
                                        }
                                    } else {
                                        $current_profit = (float)$row['profit'];
                                        $days_left = 0;
                                        $sec = '<span class="status-completed"><i class="fas fa-check-circle mr-1 text-yellow-400"></i> Completed / Stopped</span>';
                                    }
                            ?>
                            <tr>
                                <td><span class="font-semibold text-primary"><?php echo htmlspecialchars($pname); ?></span> (<?php echo $increase; ?>%)</td>
                                <td class="text-green-400 font-medium">$<?php echo number_format($current_profit, 2); ?></td>
                                <td><?php echo date('Y-m-d H:i', $start_ts); ?></td>
                                <td><?php echo $end_date_str; ?></td>
                                <td><?php echo $days_left; ?> Days</td>
                                <td>$<?php echo number_format($usd, 2); ?></td>
                                <td><?php echo $sec; ?></td>
                            </tr>
                            <?php
                                }
                            } else {
                                $is_yes = 0;
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <?php if($is_yes == 0): ?>
                        <div class="text-center py-8 text-gray-400">
                            <i class="fas fa-wallet text-4xl mb-4"></i>
                            <p class="text-lg">You have no active investments</p>
                            <a href="packages.php" class="mt-4 inline-block px-6 py-2 bg-primary text-darkbg rounded hover:bg-yellow-600 transition">
                                Explore Investment Plans
                            </a>
                        </div>
                    <?php endif; ?>
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