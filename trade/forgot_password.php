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

$email_err = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty($_POST["email"])) {
        $email_err = "Email is required";
    } else {
        $email = test_input($_POST["email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Invalid email format"; 
        }
    }
    
    if(empty($email_err)) {
        $email = $link->real_escape_string($_POST['email']);
        
        $sql1 = "SELECT * FROM users WHERE email='$email'";
        $result1 = $link->query($sql1);
        
        if(mysqli_num_rows($result1) > 0) {
            $row = mysqli_fetch_assoc($result1);
            $password = $row['password'];
            $username = $row['username'];
            
        
            $mail = new PHPMailer(true);
            
            try {
                
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST') ?: '';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USERNAME') ?: '';
                $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            
				$mail->Port       = 465;   
                
          
                $mail->setFrom(getenv('SMTP_USERNAME') ?: '', 'Redman Finance');
                $mail->addAddress($email);
                
             
                $mail->isHTML(true);
                $mail->Subject = 'Password Recovery - Redman Finance';
                
                // Email body with dark theme styling
                $mail->Body = '
                <div style="background: #0D1627; color: #ffffff; padding: 20px; font-family: Arial, sans-serif;">
                    <div style="max-width: 600px; margin: 0 auto; border: 1px solid #f4d35e; border-radius: 5px;">
                        <div style="background: #1a2639; padding: 20px; text-align: center; border-bottom: 2px solid #f4d35e;">
                            <img src="https://redmanfinance.org.ng/public/redman%20finance.svg" alt="Redman Finance" style="height: 50px;">
                            <h2 style="color: #f4d35e; margin-top: 10px;">Password Recovery</h2>
                        </div>
                        
                        <div style="padding: 20px;">
                            <p>Hello '.$username.',</p>
                            <p>You requested a password recovery for your Redman Finance account.</p>
                            
                            <div style="background: #1a2639; padding: 15px; margin: 20px 0; border-left: 3px solid #f4d35e;">
                                <p style="margin: 0;">Your password is: <strong>'.$password.'</strong></p>
                            </div>
                            
                            <p>For security reasons, we recommend changing your password after logging in.</p>
                            <p>If you didn\'t request this, please contact our support team immediately.</p>
                        </div>
                        
                        <div style="background: #1a2639; padding: 15px; text-align: center; border-top: 1px solid #f4d35e; font-size: 12px;">
                            <p>© '.date('Y').' Redman Finance. All rights reserved.</p>
                            <p>This is an automated message, please do not reply directly to this email.</p>
                        </div>
                    </div>
                </div>';
                
                $mail->AltBody = "Password Recovery\n\nHello $username,\n\nYour Redman Finance account password is: $password\n\nPlease login and change your password for security.\n\nIf you didn't request this, please contact support immediately.";
                
                $mail->send();
                $msg = "We've sent your password to your email address. Please check your inbox (and spam folder).";
            } catch (Exception $e) {
                $msg = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $msg = "Email not found in our system!";
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
    <title>Redman Finance | Forgot Password</title>
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
                <h2 class="mt-4 text-lg font-bold text-primary">Password Recovery</h2>
            </div>
            
            <form class="px-8 py-6" action="" method="POST">
                <?php if($msg != ""): ?>
                    <div class="mb-4 p-4 bg-yellow-900 text-yellow-100 rounded-lg">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                    <input id="email" name="email" type="email" required 
                           class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                           placeholder="Enter your registered email"
                           value="<?php echo htmlspecialchars($email); ?>">
                    <?php if($email_err): ?>
                        <p class="mt-1 text-sm text-red-400"><?php echo $email_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <button type="submit" name="reset_password" 
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-gray-900 bg-primary hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-key text-gray-900"></i>
                    </span>
                    Recover Password
                </button>
                
                <div class="mt-4 text-center text-sm text-gray-400">
                    Remember your password? 
                    <a href="login.php" class="font-medium text-primary hover:text-yellow-300">Login here</a>
                </div>
            </form>
        </div>
    </div>
    
    <footer class="py-4 bg-gray-900 text-gray-300 text-center">
        <p>&copy; <?php echo date("Y"); ?> | Redman Finance All Rights Reserved</p>
    </footer>
    
    
    <!-- jQuery CDN (if still needed for other scripts) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>