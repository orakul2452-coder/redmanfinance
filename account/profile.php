<?php
session_start();
include "../db.php";
include "../config.php";

$msg = "";
$err = "";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Authentication and data fetching
if(isset($_SESSION['email'])){
    $email = $link->real_escape_string($_SESSION['email']);
    $sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($link, $sql1);
    if(mysqli_num_rows($result) > 0){
        $row1 = mysqli_fetch_assoc($result);
        $ubalance = round($row1['walletbalance'],2);
        $udate = $row1['date'];
        $uprofit = round($row1['profit'],2);
        $fname = $row1['fname'];
        $lname = $row1['lname'];
        $username = $row1['username'];
        $id_front = $row1['id_front'];
        $id_back = $row1['id_back'];
        $referred = $row1['referred'];
    } else {
        header("location: ../login.php");
    }
} else {
    header('location: ../login.php');
    die();
}

// Handle ID upload
if (isset($_POST['upload'])) {
    $filename1 = $_FILES["idback"]["name"];
    $tempname1 = $_FILES["idback"]["tmp_name"];
    $filename2 = $_FILES["id_front"]["name"];
    $tempname2 = $_FILES["id_front"]["tmp_name"];

    $imgname1 = $email.$filename1;
    $imgname2 = $email.$filename2;

    $folder1 = "uploads/".$imgname1;
    $folder2 = "uploads/".$imgname2;
    $check1 = getimagesize($tempname1);
    $check2 = getimagesize($tempname2);
    
    if ($check1 === false || $check2 === false) {
        $err = "Please select valid image files";
    } else {
        $query = mysqli_query($link, "UPDATE users SET id_back = '$imgname1', id_front = '$imgname2' WHERE email = '$email'");
        if ($query) {
            move_uploaded_file($tempname1, $folder1);
            move_uploaded_file($tempname2, $folder2);
            $msg = "ID photos uploaded successfully";
            
            // Refresh user data
            $sql1 = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
            $result = mysqli_query($link, $sql1);
            $row1 = mysqli_fetch_assoc($result);
            $id_front = $row1['id_front'];
            $id_back = $row1['id_back'];
        } else {
            $err = "Error occurred during upload";
        }
    }
}

// Calculate total withdrawals
$wbtc1 = 0;
$sql211 = "SELECT SUM(usd) as total_value FROM btc WHERE email= '$email' and type = 'Withdrawal' and status = 'approved'";
$result211 = mysqli_query($link,$sql211);
$row11 = mysqli_fetch_assoc($result211);
if($row11['total_value'] != ""){
    $wbtc1 = $row11['total_value'];
}

// Handle profile update
$fname_err = $lname_err = $phone_err = $password_err = $username_err = "";

if(isset($_POST['submit'])){
    // Validate name
    if(empty(trim($_POST["fname"]))){
        $fname_err = "Please enter first name";     
    } else {
        $ufname = $link->real_escape_string($_POST['fname']);
    }
    
    if(empty(trim($_POST["lname"]))){
        $lname_err = "Please enter last name";     
    } else {
        $ulname = $link->real_escape_string($_POST['lname']);
    }
    
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username";     
    } else {
        $username = $link->real_escape_string($_POST['username']);
    }

    if(empty($fname_err) && empty($lname_err) && empty($username_err)){
        $sql = "UPDATE users SET fname ='$ufname', lname='$ulname', username='$username' WHERE email='$email'";
        if (mysqli_query($link, $sql)) {
            $msg = "Your details have been successfully updated";
            // Refresh user data
            $fname = $ufname;
            $lname = $ulname;
            $username = $username;
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
    <title>User Profile | Redman Finance</title>
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
        .form-input {
            background-color: #2a3a56;
            border: 1px solid #3a4a66;
            color: white;
        }
        .form-input:focus {
            border-color: #f4d35e;
            box-shadow: 0 0 0 2px rgba(244, 211, 94, 0.2);
        }
        .file-upload-btn {
            background-color: #105BAA;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .file-upload-btn:hover {
            background-color: #0c4b8f;
        }
        .id-preview {
            border: 2px dashed #3a4a66;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
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
                
                <a href="profile.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-md bg-primary text-darkbg">
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
                <h1 class="text-xl font-semibold text-gray-100">User Profile</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-300">Welcome, <?php echo $fname; ?></span>
                    <!-- Mobile menu button -->
                    <button class="md:hidden text-gray-400 hover:text-white focus:outline-none">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Profile Form Column -->
                <div class="col-span-1 md:col-span-2">
                    <div class="card p-6 mb-6">
                        <h2 class="text-xl font-bold text-primary mb-4">Edit Profile</h2>
                        
                        <?php if($err): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                            <p><?php echo $err; ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($msg): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                            <p><?php echo $msg; ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- ID Verification Section -->
                        <?php if(empty($id_front) || empty($id_back)): ?>
                        <div class="mb-6 p-4 bg-blue-900 bg-opacity-20 rounded-lg">
                            <h3 class="text-lg font-semibold text-primary mb-2">Verify Your Photo ID</h3>
                            <p class="mb-4">Due to financial regulations, we require verification of your ID. This helps prevent someone else from creating an account in your name.</p>
                            <p>After completing this step, you'll be ready to start investing with Redman Finance.</p>
                            
                            <form action="" method="post" enctype="multipart/form-data" class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-gray-300 mb-2">Front ID Photo</label>
                                    <div class="flex items-center">
                                        <label class="file-upload-btn">
                                            <i class="fas fa-upload mr-2"></i>
                                            <span>Select Front ID</span>
                                            <input type="file" name="id_front" class="hidden" id="upfront" onchange="previewImage(this, 'previewfront')">
                                        </label>
                                        <span class="ml-3 text-gray-400" id="frontFileName"></span>
                                    </div>
                                    <div id="previewfront" class="id-preview mt-2 hidden"></div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-300 mb-2">Back ID Photo</label>
                                    <div class="flex items-center">
                                        <label class="file-upload-btn">
                                            <i class="fas fa-upload mr-2"></i>
                                            <span>Select Back ID</span>
                                            <input type="file" name="idback" class="hidden" id="upback" onchange="previewImage(this, 'previewback')">
                                        </label>
                                        <span class="ml-3 text-gray-400" id="backFileName"></span>
                                    </div>
                                    <div id="previewback" class="id-preview mt-2 hidden"></div>
                                </div>
                                
                                <button type="submit" name="upload" class="w-full py-2 px-4 bg-primary text-darkbg font-medium rounded hover:bg-yellow-600 transition">
                                    <i class="fas fa-check-circle mr-2"></i> Upload ID Photos
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Profile Edit Form -->
                        <form action="" method="post">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-gray-300 mb-2">First Name</label>
                                    <input name="fname" type="text" class="w-full px-4 py-2 form-input rounded" 
                                           placeholder="First name" value="<?php echo $fname; ?>" required>
                                    <?php if($fname_err): ?>
                                    <p class="text-red-500 text-sm mt-1"><?php echo $fname_err; ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-300 mb-2">Last Name</label>
                                    <input name="lname" type="text" class="w-full px-4 py-2 form-input rounded" 
                                           placeholder="Last name" value="<?php echo $lname; ?>" required>
                                    <?php if($lname_err): ?>
                                    <p class="text-red-500 text-sm mt-1"><?php echo $lname_err; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-300 mb-2">Username</label>
                                <input type="text" name="username" class="w-full px-4 py-2 form-input rounded" 
                                       placeholder="Username" value="<?php echo $username; ?>" required>
                                <?php if($username_err): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $username_err; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-300 mb-2">Email Address</label>
                                <input type="email" class="w-full px-4 py-2 form-input rounded bg-gray-700" 
                                       value="<?php echo $email; ?>" disabled>
                            </div>
                            
                            <button type="submit" name="submit" class="w-full py-2 px-4 bg-primary text-darkbg font-medium rounded hover:bg-yellow-600 transition">
                                <i class="fas fa-save mr-2"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Wallet Info Column -->
                <div class="col-span-1">
                    <div class="card p-6 mb-6">
                        <h2 class="text-xl font-bold text-primary mb-4">Wallet Summary</h2>
                        
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-gray-300 mb-1">Current Balance</h3>
                                <p class="text-2xl font-bold">$<?php echo number_format($ubalance, 2); ?></p>
                            </div>
                            
                            <div class="border-t border-gray-700 pt-4">
                                <h3 class="text-gray-300 mb-1">Total Earnings</h3>
                                <p class="text-2xl font-bold">$<?php echo number_format($uprofit, 2); ?></p>
                            </div>
                            
                            <div class="border-t border-gray-700 pt-4">
                                <h3 class="text-gray-300 mb-1">Total Withdrawn</h3>
                                <p class="text-2xl font-bold">$<?php echo number_format($wbtc1, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ID Photos Section -->
                    <?php if(!empty($id_front) && !empty($id_back)): ?>
                    <div class="card p-6">
                        <h2 class="text-xl font-bold text-primary mb-4">ID Verification</h2>
                        <p class="text-gray-300 mb-4">Your ID has been successfully verified.</p>
                        
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-gray-300 mb-2">Front ID</h3>
                                <img src="uploads/<?php echo $id_front ?>" alt="Front ID" class="w-full rounded border border-gray-700">
                            </div>
                            
                            <div>
                                <h3 class="text-gray-300 mb-2">Back ID</h3>
                                <img src="uploads/<?php echo $id_back ?>" alt="Back ID" class="w-full rounded border border-gray-700">
                            </div>
                        </div>
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
        
        // Image preview function
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const fileNameSpan = input.id === 'upfront' ? 
                document.getElementById('frontFileName') : 
                document.getElementById('backFileName');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" class="w-full h-auto rounded">`;
                    preview.classList.remove('hidden');
                }
                
                reader.readAsDataURL(input.files[0]);
                fileNameSpan.textContent = input.files[0].name;
            }
        }
    </script>
</body>
</html>