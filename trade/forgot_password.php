<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../email_notifications.php';
include_once __DIR__ . '/../db.php';

$msg = '';
$email_err = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmail = $_POST['email'] ?? '';
    $email = is_string($submittedEmail) ? trim($submittedEmail) : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = 'Enter a valid email address.';
    } else {
        $msg = 'If an account uses that email address, we sent a password reset link. Check your inbox and spam folder.';
        $lookup = $link->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
        if (!$lookup) {
            throw new RuntimeException('Unable to prepare password reset lookup: ' . $link->error);
        }
        $lookup->bind_param('s', $email);
        if (!$lookup->execute()) {
            throw new RuntimeException('Unable to look up password reset request: ' . $lookup->error);
        }
        $lookup->bind_result($userId, $username);
        $accountFound = $lookup->fetch();
        $lookup->close();

        if ($accountFound) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $saveToken = $link->prepare(
                'UPDATE users
                 SET password_reset_token_hash = ?, password_reset_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
                 WHERE id = ?'
            );
            if (!$saveToken) {
                throw new RuntimeException('Unable to prepare password reset token update: ' . $link->error);
            }
            $saveToken->bind_param('si', $tokenHash, $userId);
            if (!$saveToken->execute()) {
                throw new RuntimeException('Unable to save password reset token: ' . $saveToken->error);
            }
            $saveToken->close();

            $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://redmanfinance.onrender.com', '/');
            $resetUrl = $baseUrl . '/trade/reset_password.php?token=' . rawurlencode($token);
            $safeUsername = htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8');
            $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
            try {
                redman_email_send_with_smtp_fallback(static function () use ($email, $safeUsername, $safeResetUrl, $username, $resetUrl) {
                    $mail = new PHPMailer(true);
                    $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: (getenv('SMTP_USERNAME') ?: ''), getenv('MAIL_FROM_NAME') ?: 'Redman Finance');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Reset your Redman Finance password';
                    $mail->Body = '
                        <div style="background:#0D1627;color:#fff;padding:20px;font-family:Arial,sans-serif">
                            <div style="max-width:600px;margin:0 auto;border:1px solid #f4d35e;border-radius:5px;padding:24px">
                                <h2 style="color:#f4d35e">Password reset</h2>
                                <p>Hello ' . $safeUsername . ',</p>
                                <p>We received a request to reset your Redman Finance password.</p>
                                <p><a href="' . $safeResetUrl . '" style="background:#f4d35e;color:#0D1627;padding:12px 20px;text-decoration:none;border-radius:5px">Choose a new password</a></p>
                                <p>This link expires in one hour and can only be used once. If you did not request this, ignore this email.</p>
                            </div>
                        </div>';
                    $mail->AltBody = "Hello $username,\n\nReset your Redman Finance password using this one-time link:\n$resetUrl\n\nThe link expires in one hour. If you did not request this, ignore this email.";
                    return $mail;
                }, 'Password reset email for user ' . $userId);
            } catch (\Throwable $exception) {
                error_log('Password reset email could not be delivered: ' . $exception->getMessage());
            }
        }
    }
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
                        <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
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

    <a href="tel:+18167488978" aria-label="Call Redman Finance support" title="Call Redman Finance support" class="whatsapp-float">
        <i class="fa-solid fa-phone text-2xl"></i>
    </a>
    
    <!-- jQuery CDN (if still needed for other scripts) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>