<?php
session_start();

include "../db.php";
include "../config.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;

$username_err = $password_err= ""; 
$username = $password= "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
 
  if (empty($_POST["username"])) {
    $username_err = "Username is required";
  } else {
    $username = test_input($_POST["username"]);
  }
  
  if (empty($_POST["password"])) {
    $password_err = "Password is required";
  } else {
    $password = test_input($_POST["password"]);
  }
    
  $username = $link->real_escape_string($_POST['username']);
  $password = $link->real_escape_string($_POST['password']);

  if($username == "" || $password == ""){
    $msg = "Username or Password fields cannot be empty!";
  } else {
    $sql1 = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result1 = $link->query($sql1);
    if(mysqli_num_rows($result1) > 0){
      $row = mysqli_fetch_array($result1);
      $_SESSION['email'] = $row['email'];
      
      if (empty($row['id_front']) || $row['id_front'] == null || empty($row['id_back']) || $row['id_back'] == null) {
        header("location: ../account/profile.php");
      } else {
        header("location: ../account/");
      }
      
      // Email sending code remains the same
      // ...
    } else {
      $msg = "Email or Password incorrect!";
    }
  }
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
            <?php echo $msg; ?>
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