
<?php

session_start();


include "../../config/db.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;

$username=$_GET['username'];
$email=$_GET['email'];


if(isset($_POST['submit'])){




$eth =$link->real_escape_string( $_POST['eth']);
$usd =$link->real_escape_string( $_POST['usd']);
$btctnx =$link->real_escape_string($_POST['btctnx']);
$email =$link->real_escape_string($_POST['email']);
$status =$link->real_escape_string($_POST['status']);

$tnx = uniqid('tnx');


if($eth == "" || $usd == "" ||  $btctnx == "" ){
			$msg = "No Field should be left empty!";

	}else{


$sql = "INSERT INTO btc (eth,usd,btctnx,email,status,tnxid)
VALUES ('$eth','$usd','$btctnx','$email','$status','$tnx')";

if (mysqli_query($link, $sql)) {

  require_once __DIR__ . '/../../email_notifications.php';
  try {
    redman_email_send_with_smtp_fallback(static function () use ($email, $username, $usd, $eth, $tnx) {
      $mail = new PHPMailer\PHPMailer\PHPMailer(true);
      $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: (getenv('SMTP_USERNAME') ?: ''));
      $mail->addAddress($email, $username);
      $mail->Subject = 'Investment transaction update';
      $mail->isHTML(true);
      $mail->Body = "<div style='background-color:#fff;color:black'><h2>Thank you for investing with Redman Finance</h2><p>Your order of $usd USD worth of $eth ETH has been submitted. Transaction ID: $tnx. It will be credited once confirmed.</p></div>";
      $mail->AltBody = "Your order of $usd USD worth of $eth ETH has been submitted. Transaction ID: $tnx. It will be credited once confirmed.";
      return $mail;
    }, 'Admin investment transaction email');
    $msg = "Your order of $usd USD worth of $eth ETH has been submitted, transaction ID $tnx.";
  } catch (Throwable $mailError) {
    error_log('Admin investment transaction email failed: ' . $mailError->getMessage());
    $msg = 'The transaction was recorded, but its email notification could not be delivered.';
  }





} else {
    echo "Error: " . $sql . "<br>" . mysqli_error($link);
}
}

}
function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}
include "header.php";


    ?>





 <div class="content-wrapper">
  


  <!-- Main content -->
  <section class="content">



   


        <?php
function getPrice($url){
  $decode = file_get_contents($url);
return json_decode($decode, true);
}
$ethUsd  = getPrice('https://www.cryptonator.com/api/ticker/eth-usd/');
$ethPrice = $ethUsd["ticker"]["price"];
$ethDisplay = round($ethPrice, 2);

?>

<script>
function btcconverter(input){
var price = "<?php echo $ethDisplay; ?>";
var output = input.value / price;
var out= document.getElementById('eth');
out.value=output;
}

</script>

<div class="col-md-12 col-sm-12 col-sx-12">
          <div class="box box-default">
            <div class="box-header with-border">

          <h4 align="center"><i class="fa fa-refresh"></i> Coin2Wealth  Ethereum Payment Process</h4>
</br>
          <div class="btn-group">
                  <button type="button" class="btn btn-primary btn-flat">Select deposit mode to use</button>
                  <button type="button" class="btn btn-primary btn-flat dropdown-toggle" data-toggle="dropdown">
                    <span class="caret"></span>
                    <span class="sr-only">Toggle Dropdown</span>
                  </button>
                  <ul class="dropdown-menu" role="menu">
                  <li><a href="#">Select deposit mode to use</a></li>
                    <li><a href="deposit.php?username=<?php  echo $_SESSION['username']?>&email= <?php  echo $_SESSION['email']?>&sessions= <?php  echo $_SESSION['session']?>">Bitcoin Payment</a></li>
                    <li><a href="transfer.php?username=<?php  echo $_SESSION['username']?>&email= <?php  echo $_SESSION['email']?>&sessions= <?php  echo $_SESSION['session']?>">Bank Transfer</a></li>
                   
                  </ul>
                </div>
         

 
          <hr></hr>
           <h5>Make payment to the below Ethereum wallet</h5>
          
          <input type="text" class="form-control" value="0x5e6c777ccc62bb33dcf5eb5cb047fb3c71fd3329" id="myInput" readonly>
          </br>
<button onclick="myFunction()" class="btn btn-info">Copy Ethereum Address</button>
<script>
function myFunction() {
  var copyText = document.getElementById("myInput");
  copyText.select();
  document.execCommand("copy");
  alert("Copied the wallet address: " + copyText.value);
}
</script>
          
        
          
            <div class="box-header with-border">
            
            <?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
          </br>

     <form class="form-horizontal" action="ethereum.php?username=<?php  echo $_SESSION['username']?>&email=<?php  echo $_SESSION['email']?>&sessions= <?php  echo $_SESSION['session']?>" method="POST" >

       <div class="form-group">
        <input type="double" id="eth" name="eth" placeholder="Value in ETHEREUM is displayed here" readonly="true" class="form-control">
      </div>
        <div class="form-group">
        <input type="double" onchange="btcconverter(this);" onkeyup="btcconverter(this);" id="usd" name="usd" placeholder="Amount in USD" class="form-control">
        </div>
       
        <div class="form-group">
        <input type="text"  name="btctnx" placeholder="Paste the transferred eth transaction ID " class="form-control">
        </div>

        <input type="hidden"  name="email" value="<?php  echo $_SESSION['email']?>" class="form-control">

        <input type="hidden"  name="status" value="pending" class="form-control">

      <button style="width:100%" type="submit" class="btn btn-success" name="submit" >Deposit</button>


    </form>


    </div>
   </div>

   </div>
  </div>
  </section>
</div>

