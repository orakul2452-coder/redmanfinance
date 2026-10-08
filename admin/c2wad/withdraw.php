<?php
session_start();

include "../../conn.php";
include "../../config.php";

if(!isset($_SESSION['uid'])){
	header("location:../c2wadmin/signin.php");
	exit();
}

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if(isset($_POST['backdate'])){
  $transactionId = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
  $dateInput = trim($_POST['transaction_date'] ?? '');
  $transactionDate = DateTime::createFromFormat('Y-m-d\TH:i', $dateInput);
  $dateErrors = DateTime::getLastErrors();
  if($transactionId < 1 || !$transactionDate || ($dateErrors && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $transactionDate->format('Y-m-d\TH:i') !== $dateInput){
    $msg = "Enter a valid transaction date and time.";
  } elseif($transactionDate->getTimestamp() > time()){
    $msg = "Transaction history dates cannot be in the future.";
  } else {
    $formattedDate = $transactionDate->format('Y-m-d H:i:s');
    $updateDate = $link->prepare("UPDATE btc SET date = ? WHERE id = ? AND type = 'Withdrawal'");
    if($updateDate && $updateDate->bind_param('si', $formattedDate, $transactionId) && $updateDate->execute()){
      $msg = "Withdrawal transaction date updated.";
    } else {
      $msg = "Unable to update withdrawal transaction date.";
    }
  }
}

if(isset($_POST['approve'])){
	
	$tnx = $_POST['tnx'];
	$moni = $_POST['moni'];
	$email = $_POST['email'];
  $umode = $_POST['umode'];
	

		
		$sql1 = "UPDATE btc SET status = 'approved'  WHERE id = '$tnx'";
		
		$sql2= "SELECT * FROM btc WHERE id = '$tnx'";
  $result2 = mysqli_query($link,$sql2);
  if(mysqli_num_rows($result2) > 0){
   $row = mysqli_fetch_assoc($result2);
   $row['status'];
 
  }
  
  $sqlus= "SELECT * FROM users WHERE email = '$email'";
  $resultus = mysqli_query($link,$sqlus);
  if(mysqli_num_rows($resultus) > 0){
   $rowus = mysqli_fetch_assoc($resultus);
   $usernamewtc = $rowus['username'];
 
  }
 
if(isset($row['status']) &&  $row['status']== "approved"){
	
	$msg = "Transaction already approved!";

}else{
		
		if(mysqli_query($link, $sql1)){
	
		$msg = "transaction approved successfully";
if($msg = "transaction approved successfully"){
		
		
		
		include_once "PHPMailer/PHPMailer.php";
    require_once 'PHPMailer/Exception.php';
    
    $mail= new PHPMailer();
     $mail->setFrom($emaila);
   $mail->FromName = $name;
    $mail->addAddress($email, $usernamewtc);
    $mail->Subject = "Withdrawal Request Approval";
    $mail->isHTML(true);
    $mail->Body = '
    
    
  <div style="width: 100%;height: 100%; font-family: sans-serif; font-weight: 100;" class="be_container"> 
 
 <div style="max-width: 600px;margin: 0px auto;padding: 30px;"class="be_inner_containr"> <div class="be_header">
 
 
 
 <div class="be_user" style="float: left"> <p>Dear: '.$usernamewtc.'</p> </div> 
 
 <div style="clear: both;"></div> 
 
 <div class="be_bluebar" style=" padding: 20px; margin-top: 10px;">
 
 <h1>Withdrawal Approval</h1>
 
 </div> </div> 
 
 <div class="be_body" style="color: #FF6600;padding: 20px;"> <p style="line-height: 25px; color:#FF6600;"> 
 
Your withdrawal request of '.$moni.' USD worth of '.$umode.' has been approved. Thank for investing in us.
 
 </p>
 
 <div class="be_footer">
 <div style="border-bottom: 1px solid #ccc;"></div>
 
 
 <div class="be_bluebar" style=" padding: 20px; margin-top: 10px;">
 
 <p>
 Copyright ©'.$cy.' '.$name.'. </p> <div class="be_logo" style=" width:60px;height:40px;float: right;"> </div> </div> </div> </div></div>';
     
    
    if($mail->send()){
  
      
    }
} else {
    $msg = "transaction was not approved! ";
}
		}
}
}



if(isset($_POST['delete'])){
	
	$tnx = $_POST['tnx'];
	
$sql = "DELETE FROM btc WHERE id='$tnx'";

if (mysqli_query($link, $sql)) {
    $msg = "Transaction deleted successfully!";
} else {
    $msg = "Transaction not deleted! ";
}
}
	
   
 


include 'header.php';





?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
  
  

  <link rel="stylesheet" href=" https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  <link rel="stylesheet" href=" https://cdn.datatables.net/1.10.19/css/dataTables.jqueryui.min.css">
  <link rel="stylesheet" href=" https://cdn.datatables.net/buttons/1.5.6/css/buttons.jqueryui.min.css">



  

  <link rel="stylesheet" href=" https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href=" https://cdn.datatables.net/buttons/1.5.6/css/buttons.bootstrap.min.css">
  <link rel="stylesheet" href="">
 
  
    
    



  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.js"></script>
 

  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.19/js/dataTables.jqueryui.min.js"></script>
  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.5.6/js/dataTables.buttons.min.js"></script>

  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.jqueryui.min.js"></script>
   
  <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
  <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.html5.min.js"></script>
  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.print.min.js"></script>
  <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.colVis.min.js"></script>
  
     
 <div class="content-wrapper">
  


  <!-- Main content -->
  <section class="content">



   <style>
 
	
   </style>


<div style="width:100%">
          <div class="box box-default">
            <div class="box-header with-border">

	<div class="row">
	

		 <h2 class="text-center">WITHDRAWAL MANAGEMENT</h2>
		  </br>

</br>
 <?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
         
<div class="col-md-12 col-sm-12 col-sx-12">
               <div class="table-responsive">
                     <table class="display"  id="example">



					<thead>

						<tr class="info">
						<th>Email</th>
            <th>Mode</th>
						<th>Wallet/Account Address</th>
						<th style="display:none;"></th>
						<th style="display:none;"></th>
            <th style="display:none;"></th>
						<th style="display:none;"></th>
			
							<th>Amount</th>
                             <th>Status</th>
							<th>Date</th>
                                <th>Action</th>
                                 <th>Action</th>
                                

						</tr>
					</thead>



					<tbody>
					<?php $sql= "SELECT * FROM btc WHERE type = 'Withdrawal' ORDER BY id DESC";
			  $result = mysqli_query($link,$sql);
			  if(mysqli_num_rows($result) > 0){
				  while($row = mysqli_fetch_assoc($result)){   

$row['status'];
   
   
if(isset($row['status']) &&  $row['status']== 'approved'){
	
	
	$sec = 'Approved &nbsp;&nbsp;<i style="background-color:green;color:#fff; font-size:20px;" class="fa  fa-check" ></i>';

}else{
$sec ='Pending &nbsp;&nbsp;<i class="fa  fa-refresh" style=" font-size:20px;color:red"></i>';

}

				  ?>

						<tr class="primary">
						<form action="withdraw.php" method="post">
						
                          <td><?php echo $row['email'];?></td>
                            <td><?php echo $row['mode'];?></td>
                            <td><?php echo $row['account'];?></td>
						  
						  <td style="display:none;"><input type="hidden" name="transaction_id" value="<?php echo (int)$row['id'];?>"> </td>
						  <td style="display:none;"><input type="hidden" name="email" value="<?php echo $row['email'];?>"> </td>
							<td style="display:none;"><input type="hidden" name="moni" value="<?php echo $row['usd'];?>"> </td>
              <td style="display:none;"><input type="hidden" name="umode" value="<?php echo $row['mode'];?>"> </td>
							
							<td style="display:none;"><input type="hidden" name="tnx" value="<?php echo $row['id'];?>"> </td>
						  
							<td>$<?php echo $row['usd'];?></td>
							<td><?php echo $sec ;?></td>
              
        <td>
          <?php echo htmlspecialchars($row['date']); ?><br>
          <input type="datetime-local" name="transaction_date" value="<?php echo htmlspecialchars(date('Y-m-d\\TH:i', strtotime($row['date']))); ?>" required style="max-width:190px; color:#222;">
          <button class="btn btn-default btn-xs" type="submit" name="backdate">Save date</button>
        </td>
			  
                             <td><button class="btn btn-primary" type="submit" name="approve" ><span class="glyphicon glyphicon-check"> Approve</span></button></td>
							
    <td><button type="submit" name="delete" class="btn btn-danger"><span class="glyphicon glyphicon-trash"> Delete</span></button></td>
</form>


						</tr>
					  <?php
 }
			  }
			  ?>
					</tbody>



				</table>
</div>
          </div>

		  </div>
          <!-- /top tiles -->

          </div>

   

    </body>
              </div>
            </div>


              </div>


          <br />







    </body>
              </div>
            </div>





          </section>

   </div>
  </div>
</div>


  </body>
</html>

     
<script>
$(document).ready(function() {
    var table = $('#example').DataTable( {
        lengthChange: false,
        buttons: [ 'copy', 'excel', 'pdf', 'colvis' ],
       
    } );
    

    table.buttons().container()
        .insertBefore( '#example_filter' );

        table.buttons().container()
        .appendTo( '#example_wrapper .col-sm-12:eq(0)' );
} );
</script>






<script>
$(document).ready(function () {
        $('#table')
                .dataTable({
                    "responsive": true,
                    
                });

				
    });



				</script>

