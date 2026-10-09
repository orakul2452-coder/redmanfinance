<?php
session_start();
include "../db.php";
include "../config.php";

$msg = "";
if (!isset($_SESSION['email'])) {
    header("Location: ../trade/login.php");
    exit();
}

$email = $_SESSION['email'];
$_SESSION['wallet_topup_csrf'] = $_SESSION['wallet_topup_csrf'] ?? bin2hex(random_bytes(32));
$userQuery = $link->prepare("SELECT walletbalance, profit, refcode, referred, username FROM users WHERE email = ? LIMIT 1");
if (!$userQuery) {
    http_response_code(500);
    exit("Unable to load wallet account.");
}
$userQuery->bind_param('s', $email);
$userQuery->execute();
$row1 = $userQuery->get_result()->fetch_assoc();
if (!$row1) {
    header("Location: ../trade/login.php");
    exit();
}

$pdbalance = (float)$row1['walletbalance'];
$pdprofit = (float)$row1['profit'];
$depositCurrencies = [];
$walletOptions = $link->query("SELECT name, address FROM wallet WHERE name IS NOT NULL AND TRIM(name) <> '' AND address IS NOT NULL AND TRIM(address) <> '' ORDER BY id DESC");
if ($walletOptions) {
    while ($walletOption = $walletOptions->fetch_assoc()) {
        $currencyName = trim((string)$walletOption['name']);
        $currencyKey = strtolower($currencyName);
        if (!isset($depositCurrencies[$currencyKey])) {
            $depositCurrencies[$currencyKey] = [
                'name' => $currencyName,
                'address' => trim((string)$walletOption['address']),
            ];
        }
    }
}
$withdrawnQuery = $link->prepare("SELECT COALESCE(SUM(usd), 0) AS total_value FROM btc WHERE type = 'Withdrawal' AND email = ? AND status = 'approved'");
$withdrawnQuery->bind_param('s', $email);
$withdrawnQuery->execute();
$wbtc1 = (float)$withdrawnQuery->get_result()->fetch_assoc()['total_value'];

if (isset($_POST['submit'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $amount = filter_var($_POST['usd'] ?? null, FILTER_VALIDATE_FLOAT);
    $paymentReference = trim($_POST['btctnx'] ?? '');
    $selectedCurrency = trim((string)($_POST['currency'] ?? ''));
    $selectedDepositCurrency = $depositCurrencies[strtolower($selectedCurrency)] ?? null;

    if (!hash_equals($_SESSION['wallet_topup_csrf'], $csrfToken)) {
        $msg = "Your request expired. Reload the page and try again.";
    } elseif (!$selectedDepositCurrency) {
        $msg = "Choose a supported payment currency.";
    } elseif ($amount === false || $amount <= 0 || $paymentReference === '' || strlen($paymentReference) > 200) {
        $msg = "Enter a valid amount and payment transaction ID.";
    } else {
        $transactionId = 'tnx' . bin2hex(random_bytes(12));
        $plan = 'Wallet Top-up';
        $coinType = $selectedDepositCurrency['name'];
        $allAmount = '';
        $mode = $selectedDepositCurrency['name'];
        $type = 'Wallet Deposit';
        $status = 'pending';
        $comment = '';
        $insert = $link->prepare("INSERT INTO btc (plan, cointype, allamount, mode, usd, type, email, status, account, comment, tnxid, refcode, referred) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($insert && $insert->bind_param('ssssdssssssss', $plan, $coinType, $allAmount, $mode, $amount, $type, $email, $status, $paymentReference, $comment, $transactionId, $row1['refcode'], $row1['referred']) && $insert->execute()) {
            $msg = "Your wallet top-up request was submitted and is pending confirmation. Reference: " . htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8');
        } else {
            $msg = "Unable to submit the wallet top-up request. Please try again.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <link rel="icon" type="image/png" href="assets/img/favicon.ico">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

        <title>Wallet AliCryptoFx</title>

        <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
        <meta name="viewport" content="width=device-width" />


        <!-- Bootstrap core CSS     -->
        <link href="assets/css/bootstrap.min.css" rel="stylesheet" />

        <!-- Animation library for notifications   -->
        <link href="assets/css/animate.min.css" rel="stylesheet"/>

        <!--  Light Bootstrap Table core CSS    -->
        <link href="assets/css/light-bootstrap-dashboard.css?v=1.4.0" rel="stylesheet"/>


        <!--  CSS for Demo Purpose, don't include it in your project     -->
        <link href="assets/css/demo.css" rel="stylesheet" />


        <!--     Fonts and icons     -->
        <link href="http://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
        <link href='http://fonts.googleapis.com/css?family=Roboto:400,700,300' rel='stylesheet' type='text/css'>
        <link href="assets/css/pe-icon-7-stroke.css" rel="stylesheet" />

        <style>
            :root {
                --wallet-bg: #0D1627;
                --wallet-panel: #1A2639;
                --wallet-border: #2a3a56;
                --wallet-gold: #f4d35e;
                --wallet-text: #ffffff;
                --wallet-muted: #aab4c4;
            }

            html,
            body,
            .wrapper,
            .main-panel {
                min-height: 100%;
                background: var(--wallet-bg) !important;
                color: var(--wallet-text) !important;
            }

            body,
            .main-panel,
            .content {
                color: var(--wallet-text);
            }

            .sidebar,
            .sidebar[data-color="orange"] {
                background: var(--wallet-bg) !important;
                border-right: 1px solid var(--wallet-border);
            }

            .wrapper .sidebar:after {
                background: var(--wallet-panel) !important;
                opacity: 1 !important;
            }

            .wrapper .sidebar:before {
                display: none !important;
            }

            .sidebar .nav li a,
            .sidebar .nav li a p,
            .sidebar .nav li a i {
                color: var(--wallet-muted) !important;
            }

            .sidebar .nav li.active > a,
            .sidebar .nav li.active > a p,
            .sidebar .nav li.active > a i,
            .sidebar .nav li > a:hover,
            .sidebar .nav li > a:hover p,
            .sidebar .nav li > a:hover i {
                background: var(--wallet-gold) !important;
                color: var(--wallet-bg) !important;
            }

            .navbar,
            .navbar-default,
            .navbar-default .navbar-collapse,
            .navbar-default .navbar-form {
                background: var(--wallet-panel) !important;
                border-color: var(--wallet-border) !important;
            }

            .navbar .navbar-brand,
            .navbar .navbar-nav > li > a,
            .navbar .navbar-brand:hover {
                color: var(--wallet-text) !important;
            }

            .card,
            .card .header,
            .card .content,
            .box,
            .box-body {
                background: var(--wallet-panel) !important;
                color: var(--wallet-text) !important;
                border-color: var(--wallet-border) !important;
                box-shadow: none;
            }

            .card {
                border: 1px solid var(--wallet-border);
                border-radius: 6px;
            }

            .card .title,
            .card .header .category,
            .content p,
            label,
            th,
            td {
                color: var(--wallet-text);
            }

            .card .header .category,
            .copyright {
                color: var(--wallet-muted) !important;
            }

            .form-control,
            input.form-control {
                background: #101b2d !important;
                color: var(--wallet-text) !important;
                border: 1px solid var(--wallet-border) !important;
            }

            .form-control:focus {
                border-color: var(--wallet-gold) !important;
                box-shadow: 0 0 0 2px rgba(244, 211, 94, .18);
            }

            .btn-info,
            .btn-info:hover,
            .btn-info:focus {
                background: var(--wallet-gold) !important;
                border-color: var(--wallet-gold) !important;
                color: var(--wallet-bg) !important;
            }

            .table,
            .table > thead > tr > th,
            .table > tbody > tr > td {
                background: var(--wallet-panel) !important;
                color: var(--wallet-text) !important;
                border-color: var(--wallet-border) !important;
            }

            .table > tbody > tr:hover > td {
                background: #202f45 !important;
            }

            .footer {
                background: var(--wallet-bg) !important;
                border-color: var(--wallet-border) !important;
            }

            @media (max-width: 767px) {
                .main-panel {
                    width: 100%;
                }

                .content {
                    padding: 15px;
                }

                .table-responsive {
                    border-color: var(--wallet-border);
                }
            }
        </style>

    </head>
    <body>

        <div class="wrapper">
            <div class="sidebar" data-color="orange" data-image="">

                <!--
            
                    Tip 1: you can change the color of the sidebar using: data-color="blue | azure | green | orange | red | purple"
                    Tip 2: you can also add an image using data-image tag
	
                -->

                <div class="sidebar-wrapper">
                    <div class="logo">
                        <a href="../" class="simple-text">
                            <img class="img-responsive" alt="logo" src="../images/logo-dark.png">
                        </a>
                    </div>

                    <ul class="nav">
                        <li>
                            <a href="./"><i class="pe-7s-graph"></i><p>Dashboard</p></a>
                        </li>
                        <li>
                            <a href="profile.php"><i class="pe-7s-user"></i><p>User Profile</p></a>
                        </li>
                        <li class="active">
                            <a href="wallet.php"><i class="pe-7s-wallet"></i><p>Wallet</p></a>
                        </li>
                        <li>
                            <a href="packages.php"><i class="pe-7s-photo-gallery"></i><p>Investment plans</p></a>
                        </li>
                        <li>
                            <a href="mypackages.php"><i class="pe-7s-photo-gallery"></i><p>My Package</p></a>
                        </li>
                        <li>
                                    <a href="withdrawal.php">
                                        <i class="pe-7s-cash"></i>
                                        <p>Withdrawals</p>
                                    </a>
                                </li>
                        <li class="active-pro">
                            <a href="logout.php">
                                <i class="pe-7s-user"></i>
                                <p>Logout</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="main-panel">
                <nav class="navbar navbar-default navbar-fixed">
                    <div class="container-fluid">
                        <div class="navbar-header">
                            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                                <span class="sr-only">Toggle navigation</span>
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                                <span class="icon-bar"></span>
                            </button>
                            <a class="navbar-brand" href="#">Wallet</a>
                        </div>
                        <div class="collapse navbar-collapse">
                            <ul class="nav navbar-nav navbar-left">
                                <li>
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                        <i class="fa fa-dashboard"></i>
                                        <p class="hidden-lg hidden-md">Wallet</p>
                                    </a>
                                </li>
                            </ul>

                            <ul class="nav navbar-nav navbar-right">
                                
                                <li class="separator hidden-lg"></li>
                            </ul>
                        </div>
                    </div>
                </nav>


                <div class="content">
                    <div class="container-fluid">
                                                            <div class="row">
                                    <div class="col-md-4">
                                        <div class="card">

                                            <div class="header">
                                                <h4 class="title">Available Wallet Balance</h4>
                                                <p class="category">Funds available for investment purchases</p>
                                            </div>
                                            <div class="content" style="text-align: center">
                                                <h1>$<?php echo number_format($pdbalance, 2);?></h1>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card">

                                            <div class="header">
                                                <h4 class="title">Total Earnings</h4>
                                                <p class="category">total earnings so far</p>
                                            </div>
                                            <div class="content" style="text-align: center">
                                                <h1>$<?php echo number_format($pdprofit, 2);?></h1>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card">

                                            <div class="header">
                                                <h4 class="title">Total Withdrawn</h4>
                                                <p class="category">total earnings withdrawn so far</p>
                                            </div>
                                            <div class="content" style="text-align: center">
                                                <h1>$<?php echo $wbtc1; ?></h1>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card ">
                                    <div class="header">
                                        <h4 class="title">Add funds to wallet</h4>
                                        <p class="category">Choose a configured payment currency, send funds to its address, then submit the transaction reference for administrator review.</p>
                                    </div>
                                                                        <div class="content"></div>
                                </div>
                            </div>
                            </div>
                            <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                <div class="content">

                                <p style="text-align: center;">
                                                    <b>Payment Process</b></p>
                                                <form action="wallet.php" method="post" id="wallet-topup-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['wallet_topup_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                               
                                                <div class="row">
                                                <?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
          </br>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="deposit-currency">Payment currency</label>
                                                        <select id="deposit-currency" name="currency" class="form-control" required <?php echo empty($depositCurrencies) ? 'disabled' : ''; ?>>
                                                            <option value="">Select a configured currency</option>
                                                            <?php
                                                            $preferredCurrencies = ['bitcoin', 'ethereum', 'tether (usdt)', 'usd coin (usdc)', 'bnb', 'solana'];
                                                            $currencyKeys = array_keys($depositCurrencies);
                                                            usort($currencyKeys, static function ($left, $right) use ($preferredCurrencies) {
                                                                $leftRank = array_search($left, $preferredCurrencies, true);
                                                                $rightRank = array_search($right, $preferredCurrencies, true);
                                                                $leftRank = $leftRank === false ? PHP_INT_MAX : $leftRank;
                                                                $rightRank = $rightRank === false ? PHP_INT_MAX : $rightRank;
                                                                return $leftRank === $rightRank ? strcasecmp($left, $right) : $leftRank <=> $rightRank;
                                                            });
                                                            foreach ($currencyKeys as $currencyKey):
                                                                $currencyOption = $depositCurrencies[$currencyKey];
                                                            ?>
                                                                <option value="<?php echo htmlspecialchars($currencyOption['name'], ENT_QUOTES, 'UTF-8'); ?>" data-wallet-address="<?php echo htmlspecialchars($currencyOption['address'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($currencyOption['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="deposit-wallet-address">Payment address</label>
                                                        <input type="text" class="form-control" value="" id="deposit-wallet-address" readonly placeholder="Select a currency to view its address" <?php echo empty($depositCurrencies) ? 'disabled' : ''; ?>>
                                                        <button type="button" id="copy-deposit-address" class="btn btn-info btn-fill" disabled>Copy payment address</button>
                                                    </div>
                                                </div>
                                               
                                                            <div class="col-md-12">
                                                                <div class="form-group">
                                                                    <label>Amount in USD</label>
                                                                    <input type="number" id="usd" name="usd" placeholder="Amount in USD" class="form-control" min="0.01" step="0.01" required <?php echo empty($depositCurrencies) ? 'disabled' : ''; ?>>
                                                                   
                                                                </div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <div class="form-group">
                                                                    <label>Payment transaction reference</label>
                                                                    <input type="text" name="btctnx" placeholder="Paste the payment transaction reference" class="form-control" required <?php echo empty($depositCurrencies) ? 'disabled' : ''; ?>>
                <button type="submit" name="submit" class="btn btn-info btn-fill pull-right" <?php echo empty($depositCurrencies) ? 'disabled' : ''; ?>>Deposit</button>
                                                                </div>
                                                            </div>
                                                            <hr/>

                                                            
        

        
                                                
                                            </form>
                                            </div>
                                </div>
                                </div>
                                </div>
                                </div>
                            <center></center>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card ">
                                        <div class="header">
                                            <h4 class="title">Payment history</h4>
                                            <p class="category">A list of all your approved and pending payments.</p>
                                        </div>
                                    <div class="content table-responsive table-full-width">
                                        <table class="table table-hover table-striped">
                                        <thead>
                                        <th>Email</th>
							<th>Amount(USD)</th>
                            <th>Payment Method</th>
                            <th>Payment Reference</th>
							<th>Status</th>
							<th>Tnx ID</th>
							<th>Date</th>
                                        </thead>
                                            <tbody>
                                            <?php $sql= "SELECT * FROM btc WHERE email='$email' ORDER BY id DESC ";
			  $result = mysqli_query($link,$sql);
			  if(mysqli_num_rows($result) > 0){
                  $is_yes = 1;
				  while($row = mysqli_fetch_assoc($result)){   
					  
					 
					 
$row['status'];
   
   
if(isset($row['status']) &&  $row['status']== 'approved'){
	
	
	$sec = '<span class="badge" style="padding: 10px 15px; background-color: #29c088;">Completed</span>';

}else{
$sec ='<span class="badge" style="padding: 10px 15px; background-color: #dd2525;">Pending</span>';
}
					 
					 ?>
                                            <tr class="primary">

                                            <td><?php echo $row['email'];?></td>
							<td>$<?php echo $row['usd'];?></td>
                            <td><?php echo htmlspecialchars($row['mode'] ?? '', ENT_QUOTES, 'UTF-8');?></td>
                            <td><?php echo htmlspecialchars($row['account'] ?? '', ENT_QUOTES, 'UTF-8');?></td>
								<td><?php echo $sec;?></td>
							<td><?php echo $row['tnxid'];?></td>
							<td><?php echo $row['date'];?></td>


						</tr>
                        <?php
 }
			  }else{
                  $is_yes = 0;
              }
			  ?>
                                            </tbody>
                                        </table>

                                    </div>
                                               <?php if($is_yes==0){ echo " <center><b>You have no payment history</b></center><br>";} ?>                                </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <footer class="footer">
                        <div class="container-fluid">
                            
                            <p class="copyright pull-right">
                                        &copy; 2013 - <script>document.write(new Date().getFullYear())</script> | Turi Traders All Rights Reserved
                                    
                                    </p>
                        </div>
                    </footer>

                </div>
            </div>


    </body>

    <!--   Core JS Files   -->
    <script src="assets/js/jquery.3.2.1.min.js" type="text/javascript"></script>
    <script src="assets/js/bootstrap.min.js" type="text/javascript"></script>

    <!--  Charts Plugin -->
    <script src="assets/js/chartist.min.js"></script>

    <!--  Notifications Plugin    -->
    <script src="assets/js/bootstrap-notify.js"></script>

    <!--  Google Maps Plugin    -->
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=YOUR_KEY_HERE"></script>

    <!-- Light Bootstrap Table Core javascript and methods for Demo purpose -->
    <script src="assets/js/light-bootstrap-dashboard.js?v=1.4.0"></script>

    <!-- Light Bootstrap Table DEMO methods, don't include it in your project! -->
    <script src="assets/js/demo.js"></script>
    <script>
        const depositCurrencySelect = document.getElementById('deposit-currency');
        const depositWalletAddress = document.getElementById('deposit-wallet-address');
        const copyDepositAddress = document.getElementById('copy-deposit-address');

        if (depositCurrencySelect && depositWalletAddress && copyDepositAddress) {
            depositCurrencySelect.addEventListener('change', function () {
                const address = this.selectedOptions[0]?.dataset.walletAddress || '';
                depositWalletAddress.value = address;
                copyDepositAddress.disabled = address === '';
            });

            copyDepositAddress.addEventListener('click', async function () {
                if (!depositWalletAddress.value) return;
                try {
                    await navigator.clipboard.writeText(depositWalletAddress.value);
                } catch (error) {
                    depositWalletAddress.select();
                    document.execCommand('copy');
                }
            });
        }
    </script>

</html>
