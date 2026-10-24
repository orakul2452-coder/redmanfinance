<?php
session_start();

include "../db.php";
include "../config.php";

$msg = "";
$showResend = false;

$username_err = $password_err= ""; 
$username = $password= "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $submittedUsername = $_POST["username"] ?? "";
  $submittedPassword = $_POST["password"] ?? "";

  if (!is_string($submittedUsername) || trim($submittedUsername) === "") {
    $username_err = "Username is required";
  } else {
    $username = trim($submittedUsername);
  }
  
  if (!is_string($submittedPassword) || $submittedPassword === "") {
    $password_err = "Password is required";
  } else {
    $password = $submittedPassword;
  }

  if($username == "" || $password == ""){
    $msg = "Username or Password fields cannot be empty!";
  } else {
    $stmt = $link->prepare('SELECT * FROM users WHERE username = ? AND password = ? LIMIT 1');
    if (!$stmt) {
      throw new RuntimeException('Unable to prepare login query: ' . $link->error);
    }
    $stmt->bind_param('ss', $username, $password);
    if (!$stmt->execute()) {
      throw new RuntimeException('Unable to check login credentials: ' . $stmt->error);
    }
    $result1 = $stmt->get_result();
    $row = $result1->fetch_assoc();
    $stmt->close();

    if($row){
      if (empty($row['email_verified'])) {
        $msg = 'Please verify your email address before logging in.';
        $showResend = true;
      } else {
        $_SESSION['email'] = $row['email'];

        if (empty($row['id_front']) || $row['id_front'] == null || empty($row['id_back']) || $row['id_back'] == null) {
          header("location: ../account/profile.php");
        } else {
          header("location: ../account/");
        }
        exit();
      }
    } else {
      $msg = "Email or Password incorrect!";
    }
  }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Redman Finance | Login</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tailwind CSS CDN with custom colors -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#f4d35e',
            darkbg: '#0D1627',
          }
        }
      }
    }
  </script>
  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .whatsapp-float {
      position: fixed;
      width: 60px;
      height: 60px;
      bottom: 40px;
      left: 40px;
      background-color: #25D366;
      color: #FFF;
      border-radius: 50px;
      text-align: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
    }
    .whatsapp-float:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
    }
  </style>
</head>
<body class="bg-darkbg min-h-screen flex flex-col text-gray-200">
  <div class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
      <div class="py-8 px-8 text-center bg-gray-900">
        <a href="../index.html">
          <img src="../public/REDMAN FINANCE.svg" alt="Redman Finance Logo" class="h-16 mx-auto">
        </a>
        <h2 class="mt-4 text-lg font-bold text-primary">Login to your account</h2>
      </div>
      
      <form class="px-8 py-6" action="" method="POST">
        <?php if($msg != ""): ?>
          <div class="mb-4 p-4 bg-yellow-900 text-yellow-100 rounded-lg">
            <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            <?php if($showResend): ?>
              <p class="mt-2"><a class="underline text-primary" href="resend-verification.php">Resend verification email</a></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['registered']) && $msg == ""): ?>
          <div class="mb-4 p-4 bg-green-900 text-green-100 rounded-lg">
            Your account has been created. Verify your email before logging in.
            <?php if(($_GET['mail'] ?? '') === 'failed'): ?>
              The email could not be sent. Please use the resend link below to request a verification message.
            <?php else: ?>
              We sent your welcome and verification emails.
            <?php endif; ?>
            <p class="mt-2"><a class="underline text-primary" href="resend-verification.php">Resend verification email</a></p>
          </div>
        <?php endif; ?>

        <?php if(isset($_GET['success']) && $msg == ""): ?>
          <div class="mb-4 p-4 bg-green-900 text-green-100 rounded-lg">
            You have successfully registered. Kindly login.
          </div>
        <?php endif; ?>
        
        <div class="mb-4">
          <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
          <input id="username" name="username" type="text" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Enter your username">
          <?php if($username_err): ?>
            <p class="mt-1 text-sm text-red-400"><?php echo $username_err; ?></p>
          <?php endif; ?>
        </div>
        
        <div class="mb-4">
          <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
          <input id="password" name="password" type="password" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Enter your password">
          <?php if($password_err): ?>
            <p class="mt-1 text-sm text-red-400"><?php echo $password_err; ?></p>
          <?php endif; ?>
        </div>
        
        <div class="flex items-center justify-between mb-6">
         
          
          <div class="text-sm">
            <a href="forgot_password.php" class="font-medium text-primary hover:text-yellow-300">Forgot password?</a>
          </div>
        </div>
        
        <button type="submit" name="login" 
                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-gray-900 bg-primary hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition duration-150 ease-in-out">
        
          Login
        </button>
        
        <div class="mt-4 text-center text-sm text-gray-400">
          Don't have an account? 
          <a href="register.php" class="font-medium text-primary hover:text-yellow-300">Register here</a>
        </div>
      </form>
    </div>
  </div>
  
  <footer class="py-4 bg-gray-900 text-gray-300 text-center">
    <p>&copy; 2013 - <script>document.write(new Date().getFullYear())</script> | Redman Finance All Rights Reserved</p>
  </footer>
  
  
  <!-- jQuery CDN (if still needed for other scripts) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>