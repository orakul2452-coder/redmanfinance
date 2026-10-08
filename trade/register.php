<?php
// Include config file
include "../db.php";
include "../config.php";

// Load PHPMailer
require 'Exception.php';
require 'PHPMailer.php';
require 'SMTP.php';

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Define variables and initialize with empty values
$lname = $fname = $username = $email = $password = $cpassword = $phone = "";
$lname_err = $fname_err = $username_err = $email_err = $password_err = $cpassword_err = $phone_err = "";

if(isset($_GET['ref'])){
    $refcode = $_GET['ref'];
} else {
    $refcode = '';
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter an email.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            
            // Set parameters
            $param_email = trim($_POST["email"]);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                /* store result */
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $email_err = "This email is already taken.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
         
        // Close statement
        mysqli_stmt_close($stmt);
    }
    
    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                /* store result */
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
         
        // Close statement
        mysqli_stmt_close($stmt);
    }
    
    // Validate name
    if(empty(trim($_POST["fname"]))){
        $fname_err = "Please enter first name.";     
    } else {
        $fname = trim($_POST["fname"]);
    }

    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Check input errors before inserting in database
    if(empty($email_err) && empty($password_err) && empty($fname_err) && empty($username_err)){
        
        $mobile = $_POST['mobile'];
        $country = $_POST['country'];
        $referred = $_POST['referred'];
        $zip = $_POST['zip'];
        $account_type = $_POST['account_type'];
        $refcode = 'kllcabcdg19etsfjhdshdsh35678gwyjerehuhbdTSGSAWQUJHDCSMNBVCBNRTPZXMCBVN1234567890';
        $refcode = str_shuffle($refcode);
        $refcode = substr($refcode, 0, 10);
                 
        if($referred != '') {  
            $ref11usrid = $referred;
            $qryusrref11 = "SELECT * FROM users WHERE refcode='$ref11usrid'";
            $rslusrref11 = mysqli_query($link, $qryusrref11);
            $arrusrref11 = mysqli_fetch_assoc($rslusrref11);
            
            $referred = $arrusrref11['refcode']."_";
            
            if($arrusrref11['referred'] != '') {
                $exp_ref = explode("_", $arrusrref11['referred']);
                $qryusrref22 = "SELECT * FROM users WHERE refcode='$exp_ref[0]' or refcode='$exp_ref[1]' or refcode='$exp_ref[2]'";
                
                $rslusrref22 = mysqli_query($link, $qryusrref22);
                $arrusrref22 = mysqli_fetch_assoc($rslusrref22);
                
                $referred = $referred.$arrusrref22['refcode']."_";
                
                if($arrusrref22['referred'] != '') {
                    $exp_ref1 = explode("_", $arrusrref22['referred']);      
                    $qryusrref33 = "SELECT * FROM users WHERE refcode='$exp_ref1[0]' or refcode='$exp_ref1[1]' or refcode='$exp_ref1[2]'";
                    $rslusrref33 = mysqli_query($link, $qryusrref33);
                    $arrusrref33 = mysqli_fetch_assoc($rslusrref33);
                    
                    $referred = $referred.$arrusrref33['refcode']."_";
                }
            }
        }

        // Purge any orphan legacy data for this email to guarantee a 100% fresh account
        $clean_email = $link->real_escape_string($email);
        mysqli_query($link, "DELETE FROM investment WHERE email='$clean_email'");
        mysqli_query($link, "DELETE FROM btc WHERE email='$clean_email'");

        // Prepare an insert statement
        $sql = "INSERT INTO users (fname, lname, email, username, password, refcode, session, referred, profit, refbonus, walletbalance, pm, eth, btc, country, phone, zip, account) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
         
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
          mysqli_stmt_bind_param($stmt, "ssssssisdddsssssss", $param_fname, $param_lname, $param_email, $param_username, $param_password, $param_refcode, $param_session, $param_referred, $param_profit, $param_refbonus, $param_walletbalance, $param_pm, $param_eth, $param_btc, $param_country, $param_phone, $param_zip, $param_account);
            
            // Set parameters
            $param_fname = $fname;
          $param_lname = '';
            $param_email = $email;
            $param_username = $username;
            $param_password = $password;
            $param_refcode = $refcode;
          $param_session = 0;
            $param_referred = $referred;
          $param_profit = 0.0;
          $param_refbonus = 0.0;
          $param_walletbalance = 0.0;
          $param_pm = '';
          $param_eth = '';
          $param_btc = '';
            $param_country = $country;
            $param_phone = $mobile;
            $param_zip = $zip;
            $param_account = $account_type;
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Send welcome email
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
                    $mail->Subject = 'Welcome to Redman Finance';
                    
                    // Email body with dark theme styling
                    $mail->Body = '
                    <div style="background: #0D1627; color: #ffffff; padding: 20px; font-family: Arial, sans-serif;">
                        <div style="max-width: 600px; margin: 0 auto; border: 1px solid #f4d35e; border-radius: 5px;">
                            <div style="background: #1a2639; padding: 20px; text-align: center; border-bottom: 2px solid #f4d35e;">
                                <img src="https://redmanfinance.org.ng/public/redman%20finance.svg" alt="Redman Finance" style="height: 50px;">
                                <h2 style="color: #f4d35e; margin-top: 10px;">Welcome to Redman Finance</h2>
                            </div>
                            
                            <div style="padding: 20px;">
                                <p>Hello '.$fname.',</p>
                                <p>Thank you for registering with Redman Finance. Your account has been successfully created.</p>
                                
                                <div style="background: #1a2639; padding: 15px; margin: 20px 0; border-left: 3px solid #f4d35e;">
                                    <p style="margin: 0;">Username: <strong>'.$username.'</strong></p>
                                    <p style="margin: 0;">Email: <strong>'.$email.'</strong></p>
                                    <p style="margin: 0;">Account Type: <strong>'.$account_type.'</strong></p>
                                </div>
                                
                                <p>Redman Finance is the future of investment. Invite your family and friends to join, and you will earn bonus.</p>
                                <p>Your funds are 100% safe with Redman Finance, we guarantee you a profitable investment.</p>
                                
                                <p style="margin-top: 20px;">
                                    <a href="https://redmanfinance.org.ng/login.php" style="background-color: #f4d35e; color: #0D1627; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Login to Your Account</a>
                                </p>
                            </div>
                            
                            <div style="background: #1a2639; padding: 15px; text-align: center; border-top: 1px solid #f4d35e; font-size: 12px;">
                                <p>© '.date('Y').' Redman Finance. All rights reserved.</p>
                                <p>This is an automated message, please do not reply directly to this email.</p>
                            </div>
                        </div>
                    </div>';
                    
                    $mail->AltBody = "Welcome to Redman Finance\n\nHello $fname,\n\nThank you for registering with Redman Finance. Your account has been successfully created.\n\nUsername: $username\nEmail: $email\nAccount Type: $account_type\n\nRedman Finance is the future of investment. Invite your family and friends to join, and you will earn bonus.\n\nLogin to your account: https://redmanfinance.org.ng/login.php\n\n© ".date('Y')." Redman Finance. All rights reserved.";
                    
                    $mail->send();
                    
                    $msg = "Registration successful! We've sent a welcome email to your address.";
                    
                    // Redirect to login page
                    header("Location: login.php?success");
                    exit();
                    
                } catch (Exception $e) {
                    $msg = "Registration successful, but we couldn't send a welcome email. Please contact support.";
                    // Still redirect since registration was successful
                    header("Location: login.php?success");
                    exit();
                }
            } else {
              error_log('Registration insert failed: ' . mysqli_stmt_error($stmt));
                $msg = "Something went wrong. Please try again later.";
            }
          } else {
            error_log('Registration statement preparation failed: ' . mysqli_error($link));
            $msg = "Something went wrong. Please try again later.";
        }
         
        // Close statement
        mysqli_stmt_close($stmt);
    }
    
    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Redman Finance | Register</title>
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
    select {
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1em;
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
        <h2 class="mt-4 text-lg font-bold text-primary">Create Your Account</h2>
      </div>
      
      <form class="px-8 py-6" action="" method="POST">
        <?php if($msg != ""): ?>
          <div class="mb-4 p-4 bg-yellow-900 text-yellow-100 rounded-lg">
            <?php echo $msg; ?>
          </div>
        <?php endif; ?>
        
        <div class="mb-4">
          <label for="fname" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
          <input id="fname" name="fname" type="text" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Enter your full name" value="<?php echo htmlspecialchars($fname); ?>">
          <?php if($fname_err): ?>
            <p class="mt-1 text-sm text-red-400"><?php echo $fname_err; ?></p>
          <?php endif; ?>
        </div>
        
        <div class="mb-4">
          <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
          <input id="email" name="email" type="email" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>">
          <?php if($email_err): ?>
            <p class="mt-1 text-sm text-red-400"><?php echo $email_err; ?></p>
          <?php endif; ?>
        </div>
        
        <div class="mb-4">
          <label for="phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number</label>
          <input id="phone" name="mobile" type="text" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Enter your phone number">
        </div>
        
        <div class="mb-4">
          <label for="country" class="block text-sm font-medium text-gray-300 mb-1">Country</label>
          <select id="country" name="country" required 
                  class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm pr-8">
			<option value="">Select Country</option>
									<option value="United States">United States</option>
									<option value="United Kingdom">United Kingdom</option>
									<option value="Afghanistan">Afghanistan</option>
									<option value="Albania">Albania</option>
									<option value="Algeria">Algeria</option>
									<option value="American Samoa">American Samoa</option>
									<option value="Andorra">Andorra</option>
									<option value="Angola">Angola</option>
									<option value="Anguilla">Anguilla</option>
									<option value="Antarctica">Antarctica</option>
									<option value="Antigua and Barbuda">Antigua and Barbuda</option>
									<option value="Argentina">Argentina</option>
									<option value="Armenia">Armenia</option>
									<option value="Aruba">Aruba</option>
									<option value="Australia">Australia</option>
									<option value="Austria">Austria</option>
									<option value="Azerbaijan">Azerbaijan</option>
									<option value="Bahamas">Bahamas</option>
									<option value="Bahrain">Bahrain</option>
									<option value="Bangladesh">Bangladesh</option>
									<option value="Barbados">Barbados</option>
									<option value="Belarus">Belarus</option>
									<option value="Belgium">Belgium</option>
									<option value="Belize">Belize</option>
									<option value="Benin">Benin</option>
									<option value="Bermuda">Bermuda</option>
									<option value="Bhutan">Bhutan</option>
									<option value="Bolivia">Bolivia</option>
									<option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
									<option value="Botswana">Botswana</option>
									<option value="Bouvet Island">Bouvet Island</option>
									<option value="Brazil">Brazil</option>
									<option value="British Indian Ocean Territory">British Indian Ocean Territory</option>
									<option value="Brunei Darussalam">Brunei Darussalam</option>
									<option value="Bulgaria">Bulgaria</option>
									<option value="Burkina Faso">Burkina Faso</option>
									<option value="Burundi">Burundi</option>
									<option value="Cambodia">Cambodia</option>
									<option value="Cameroon">Cameroon</option>
									<option value="Canada">Canada</option>
									<option value="Cape Verde">Cape Verde</option>
									<option value="Cayman Islands">Cayman Islands</option>
									<option value="Central African Republic">Central African Republic</option>
									<option value="Chad">Chad</option>
									<option value="Chile">Chile</option>
									<option value="China">China</option>
									<option value="Christmas Island">Christmas Island</option>
									<option value="Cocos (Keeling) Islands">Cocos (Keeling) Islands</option>
									<option value="Colombia">Colombia</option>
									<option value="Comoros">Comoros</option>
									<option value="Congo">Congo</option>
									<option value="Congo, The Democratic Republic of The">Congo, The Democratic Republic of The</option>
									<option value="Cook Islands">Cook Islands</option>
									<option value="Costa Rica">Costa Rica</option>
									<option value="Cote D'ivoire">Cote D'ivoire</option>
									<option value="Croatia">Croatia</option>
									<option value="Cuba">Cuba</option>
									<option value="Cyprus">Cyprus</option>
									<option value="Czech Republic">Czech Republic</option>
									<option value="Denmark">Denmark</option>
									<option value="Djibouti">Djibouti</option>
									<option value="Dominica">Dominica</option>
									<option value="Dominican Republic">Dominican Republic</option>
									<option value="Ecuador">Ecuador</option>
									<option value="Egypt">Egypt</option>
									<option value="El Salvador">El Salvador</option>
									<option value="Equatorial Guinea">Equatorial Guinea</option>
									<option value="Eritrea">Eritrea</option>
									<option value="Estonia">Estonia</option>
									<option value="Ethiopia">Ethiopia</option>
									<option value="Falkland Islands (Malvinas)">Falkland Islands (Malvinas)</option>
									<option value="Faroe Islands">Faroe Islands</option>
									<option value="Fiji">Fiji</option>
									<option value="Finland">Finland</option>
									<option value="France">France</option>
									<option value="French Guiana">French Guiana</option>
									<option value="French Polynesia">French Polynesia</option>
									<option value="French Southern Territories">French Southern Territories</option>
									<option value="Gabon">Gabon</option>
									<option value="Gambia">Gambia</option>
									<option value="Georgia">Georgia</option>
									<option value="Germany">Germany</option>
									<option value="Ghana">Ghana</option>
									<option value="Gibraltar">Gibraltar</option>
									<option value="Greece">Greece</option>
									<option value="Greenland">Greenland</option>
									<option value="Grenada">Grenada</option>
									<option value="Guadeloupe">Guadeloupe</option>
									<option value="Guam">Guam</option>
									<option value="Guatemala">Guatemala</option>
									<option value="Guinea">Guinea</option>
									<option value="Guinea-bissau">Guinea-bissau</option>
									<option value="Guyana">Guyana</option>
									<option value="Haiti">Haiti</option>
									<option value="Heard Island and Mcdonald Islands">Heard Island and Mcdonald Islands</option>
									<option value="Holy See (Vatican City State)">Holy See (Vatican City State)</option>
									<option value="Honduras">Honduras</option>
									<option value="Hong Kong">Hong Kong</option>
									<option value="Hungary">Hungary</option>
									<option value="Iceland">Iceland</option>
									<option value="India">India</option>
									<option value="Indonesia">Indonesia</option>
									<option value="Iran, Islamic Republic of">Iran, Islamic Republic of</option>
									<option value="Iraq">Iraq</option>
									<option value="Ireland">Ireland</option>
									<option value="Israel">Israel</option>
									<option value="Italy">Italy</option>
									<option value="Jamaica">Jamaica</option>
									<option value="Japan">Japan</option>
									<option value="Jordan">Jordan</option>
									<option value="Kazakhstan">Kazakhstan</option>
									<option value="Kenya">Kenya</option>
									<option value="Kiribati">Kiribati</option>
									<option value="Korea, Democratic People's Republic of">Korea, Democratic People's Republic of</option>
									<option value="Korea, Republic of">Korea, Republic of</option>
									<option value="Kuwait">Kuwait</option>
									<option value="Kyrgyzstan">Kyrgyzstan</option>
									<option value="Lao People's Democratic Republic">Lao People's Democratic Republic</option>
									<option value="Latvia">Latvia</option>
									<option value="Lebanon">Lebanon</option>
									<option value="Lesotho">Lesotho</option>
									<option value="Liberia">Liberia</option>
									<option value="Libyan Arab Jamahiriya">Libyan Arab Jamahiriya</option>
									<option value="Liechtenstein">Liechtenstein</option>
									<option value="Lithuania">Lithuania</option>
									<option value="Luxembourg">Luxembourg</option>
									<option value="Macao">Macao</option>
									<option value="Macedonia, The Former Yugoslav Republic of">Macedonia, The Former Yugoslav Republic of</option>
									<option value="Madagascar">Madagascar</option>
									<option value="Malawi">Malawi</option>
									<option value="Malaysia">Malaysia</option>
									<option value="Maldives">Maldives</option>
									<option value="Mali">Mali</option>
									<option value="Malta">Malta</option>
									<option value="Marshall Islands">Marshall Islands</option>
									<option value="Martinique">Martinique</option>
									<option value="Mauritania">Mauritania</option>
									<option value="Mauritius">Mauritius</option>
									<option value="Mayotte">Mayotte</option>
									<option value="Mexico">Mexico</option>
									<option value="Micronesia, Federated States of">Micronesia, Federated States of</option>
									<option value="Moldova, Republic of">Moldova, Republic of</option>
									<option value="Monaco">Monaco</option>
									<option value="Mongolia">Mongolia</option>
									<option value="Montserrat">Montserrat</option>
									<option value="Morocco">Morocco</option>
									<option value="Mozambique">Mozambique</option>
									<option value="Myanmar">Myanmar</option>
									<option value="Namibia">Namibia</option>
									<option value="Nauru">Nauru</option>
									<option value="Nepal">Nepal</option>
									<option value="Netherlands">Netherlands</option>
									<option value="Netherlands Antilles">Netherlands Antilles</option>
									<option value="New Caledonia">New Caledonia</option>
									<option value="New Zealand">New Zealand</option>
									<option value="Nicaragua">Nicaragua</option>
									<option value="Niger">Niger</option>
									<option value="Nigeria">Nigeria</option>
									<option value="Niue">Niue</option>
									<option value="Norfolk Island">Norfolk Island</option>
									<option value="Northern Mariana Islands">Northern Mariana Islands</option>
									<option value="Norway">Norway</option>
									<option value="Oman">Oman</option>
									<option value="Pakistan">Pakistan</option>
									<option value="Palau">Palau</option>
									<option value="Palestinian Territory, Occupied">Palestinian Territory, Occupied</option>
									<option value="Panama">Panama</option>
									<option value="Papua New Guinea">Papua New Guinea</option>
									<option value="Paraguay">Paraguay</option>
									<option value="Peru">Peru</option>
									<option value="Philippines">Philippines</option>
									<option value="Pitcairn">Pitcairn</option>
									<option value="Poland">Poland</option>
									<option value="Portugal">Portugal</option>
									<option value="Puerto Rico">Puerto Rico</option>
									<option value="Qatar">Qatar</option>
									<option value="Reunion">Reunion</option>
									<option value="Romania">Romania</option>
									<option value="Russian Federation">Russian Federation</option>
									<option value="Rwanda">Rwanda</option>
									<option value="Saint Helena">Saint Helena</option>
									<option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
									<option value="Saint Lucia">Saint Lucia</option>
									<option value="Saint Pierre and Miquelon">Saint Pierre and Miquelon</option>
									<option value="Saint Vincent and The Grenadines">Saint Vincent and The Grenadines</option>
									<option value="Samoa">Samoa</option>
									<option value="San Marino">San Marino</option>
									<option value="Sao Tome and Principe">Sao Tome and Principe</option>
									<option value="Saudi Arabia">Saudi Arabia</option>
									<option value="Senegal">Senegal</option>
									<option value="Serbia and Montenegro">Serbia and Montenegro</option>
									<option value="Seychelles">Seychelles</option>
									<option value="Sierra Leone">Sierra Leone</option>
									<option value="Singapore">Singapore</option>
									<option value="Slovakia">Slovakia</option>
									<option value="Slovenia">Slovenia</option>
									<option value="Solomon Islands">Solomon Islands</option>
									<option value="Somalia">Somalia</option>
									<option value="South Africa">South Africa</option>
									<option value="South Georgia and The South Sandwich Islands">South Georgia and The South Sandwich Islands</option>
									<option value="Spain">Spain</option>
									<option value="Sri Lanka">Sri Lanka</option>
									<option value="Sudan">Sudan</option>
									<option value="Suriname">Suriname</option>
									<option value="Svalbard and Jan Mayen">Svalbard and Jan Mayen</option>
									<option value="Swaziland">Swaziland</option>
									<option value="Sweden">Sweden</option>
									<option value="Switzerland">Switzerland</option>
									<option value="Syrian Arab Republic">Syrian Arab Republic</option>
									<option value="Taiwan, Province of China">Taiwan, Province of China</option>
									<option value="Tajikistan">Tajikistan</option>
									<option value="Tanzania, United Republic of">Tanzania, United Republic of</option>
									<option value="Thailand">Thailand</option>
									<option value="Timor-leste">Timor-leste</option>
									<option value="Togo">Togo</option>
									<option value="Tokelau">Tokelau</option>
									<option value="Tonga">Tonga</option>
									<option value="Trinidad and Tobago">Trinidad and Tobago</option>
									<option value="Tunisia">Tunisia</option>
									<option value="Turkey">Turkey</option>
									<option value="Turkmenistan">Turkmenistan</option>
									<option value="Turks and Caicos Islands">Turks and Caicos Islands</option>
									<option value="Tuvalu">Tuvalu</option>
									<option value="Uganda">Uganda</option>
									<option value="Ukraine">Ukraine</option>
									<option value="United Arab Emirates">United Arab Emirates</option>
									<option value="United Kingdom">United Kingdom</option>
									<option value="United States">United States</option>
									<option value="United States Minor Outlying Islands">United States Minor Outlying Islands</option>
									<option value="Uruguay">Uruguay</option>
									<option value="Uzbekistan">Uzbekistan</option>
									<option value="Vanuatu">Vanuatu</option>
									<option value="Venezuela">Venezuela</option>
									<option value="Viet Nam">Viet Nam</option>
									<option value="Virgin Islands, British">Virgin Islands, British</option>
									<option value="Virgin Islands, U.S.">Virgin Islands, U.S.</option>
									<option value="Wallis and Futuna">Wallis and Futuna</option>
									<option value="Western Sahara">Western Sahara</option>
									<option value="Yemen">Yemen</option>
									<option value="Zambia">Zambia</option>
									<option value="Zimbabwe">Zimbabwe</option>

          </select>
        </div>
        
        <div class="mb-4">
          <label for="zip" class="block text-sm font-medium text-gray-300 mb-1">Zip Code</label>
          <input id="zip" name="zip" type="text" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Enter your zip code">
        </div>
        
        <div class="mb-4">
          <label for="account_type" class="block text-sm font-medium text-gray-300 mb-1">Account Type</label>
          <select id="account_type" name="account_type" required 
                  class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm pr-8">
            <option value="" selected>Account Type</option>
            <option value="Starter">Starter : $100 - $4,999</option>
            <option value="Advanced">Advanced : $10,000 - $9,999</option>
            <option value="Professional">Professional : $10,000 - $19,999</option>
            <option value="Ultimate">Ultimate : $20,000 - $50,000</option>
          </select>
        </div>
        
        <input type="hidden" name="referred" value="<?php echo htmlspecialchars($refcode); ?>">
        
        <div class="mb-4">
          <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
          <input id="username" name="username" type="text" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Choose a username" value="<?php echo htmlspecialchars($username); ?>">
          <?php if($username_err): ?>
            <p class="mt-1 text-sm text-red-400"><?php echo $username_err; ?></p>
          <?php endif; ?>
        </div>
        
        <div class="mb-6">
          <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
          <input id="password" name="password" type="password" required 
                 class="appearance-none relative block w-full px-3 py-3 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:z-10 sm:text-sm"
                 placeholder="Create a password (min 6 characters)">
          <?php if($password_err): ?>
            <p class="mt-1 text-sm text-red-400"><?php echo $password_err; ?></p>
          <?php endif; ?>
        </div>
        
        <button type="submit" name="register" 
                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-gray-900 bg-primary hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition duration-150 ease-in-out">
          Create Account
        </button>
        
        <div class="mt-4 text-center text-sm text-gray-400">
          Already have an account? 
          <a href="login.php" class="font-medium text-primary hover:text-yellow-300">Login here</a>
        </div>
      </form>
    </div>
  </div>
  
  <footer class="py-4 bg-gray-900 text-gray-300 text-center">
    <p>&copy; 2022 - <script>document.write(new Date().getFullYear())</script> | Redman Finance All Rights Reserved</p>
  </footer>
  
  <!-- jQuery CDN (if still needed for other scripts) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>