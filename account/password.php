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
        $udate = $row1['date'];
        $uprofit = round($row1['profit'],2);
        $upassword = $row1['password'];
    }else{
        header("location: ../login.php");
    }
}else{
    header('location: ../login.php');
    die();
}

$fname_err = $cpassword_err = $opassword_err = $password_err = "";

if(isset($_POST['submit'])){
    // Validate old password
    if(empty(trim($_POST["opassword"]))){
        $opassword_err = "Please enter old password.";     
    }else{
        $opassword = $link->real_escape_string($_POST['opassword']);
    }
    
    // Validate new password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter new password.";     
    }else{
        $password = $link->real_escape_string($_POST['password']);
    }

    // Validate confirm password
    if(empty(trim($_POST["cpassword"]))){
        $cpassword_err = "Please confirm password.";     
    }else{
        $cpassword = $link->real_escape_string($_POST['cpassword']);
    }

    if($opassword != $upassword){
        $opassword_err = "Wrong old password"; 
    }

    if(strlen($password) < 6){
        $password_err = "Password must have atleast 6 characters."; 
    }
    
    if($password != $cpassword){
        $cpassword_err = "Password did not match"; 
    }

    if(empty($cpassword_err) && empty($opassword_err) && empty($password_err)){
        $sql = "UPDATE users SET password ='$password' WHERE email='$email'";
        if (mysqli_query($link, $sql)) {
            $msg = "Password changed successfully";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/png" href="../public/REDMAN FINANCE.svg">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Change Password | Redman Finance</title>
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
                        success: '#10B981',
                        danger: '#EF4444'
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
        .form-control {
            background-color: #2a3a56;
            border: 1px solid #3a4a66;
            color: #ffffff;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            width: 100%;
        }
        .form-control:focus {
            outline: none;
            border-color: #f4d35e;
            box-shadow: 0 0 0 2px rgba(244, 211, 94, 0.25);
        }
        label {
            color: #cccccc;
            margin-bottom: 0.5rem;
            display: block;
        }
        .btn {
            background-color: #f4d35e;
            color: #0D1627;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .btn:hover {
            background-color: #e6c84d;
            transform: translateY(-1px);
        }
        .btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(244, 211, 94, 0.5);
        }
        .alert-success {
            background-color: rgba(16, 185, 129, 0.2);
            border-left: 4px solid #10B981;
            color: #ffffff;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
        .error-message {
            color: #EF4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
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
                
                <a href="mypackages.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-800">
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
                
                <a href="password.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md bg-primary text-darkbg">
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
                <h1 class="text-xl font-semibold text-gray-100">Change Password</h1>
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
            <div class="grid grid-cols-1 gap-6">
                <div class="card p-6">
                    <h2 class="text-xl font-semibold text-primary mb-6">Change Your Password</h2>
                    
                    <?php if($msg != ""): ?>
                        <div class="alert-success mb-6">
                            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($msg); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="post">
                        <div class="mb-6">
                            <label for="opassword">Old Password</label>
                            <input type="password" id="opassword" name="opassword" class="form-control" 
                                   placeholder="Enter your current password" required>
                            <?php if(!empty($opassword_err)): ?>
                                <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i> <?php echo $opassword_err; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="password">New Password</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Enter new password" required>
                                <?php if(!empty($password_err)): ?>
                                    <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i> <?php echo $password_err; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label for="cpassword">Confirm Password</label>
                                <input type="password" id="cpassword" name="cpassword" class="form-control" 
                                       placeholder="Confirm new password" required>
                                <?php if(!empty($cpassword_err)): ?>
                                    <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i> <?php echo $cpassword_err; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="submit" class="btn">
                                <i class="fas fa-save mr-2"></i> Update Password
                            </button>
                        </div>
                    </form>
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