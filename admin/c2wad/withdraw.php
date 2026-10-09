<?php
session_start();

include "../../conn.php";
include "../../config.php";

if(!isset($_SESSION['uid'])){
	header("location:../c2wadmin/signin.php");
	exit();
}

$msg = "";
$msgType = "success";
if (empty($_SESSION['withdrawal_admin_csrf'])) {
  $_SESSION['withdrawal_admin_csrf'] = bin2hex(random_bytes(32));
}
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

if (isset($_POST['approve'])) {
  $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
  $approvedAddress = trim((string)($_POST['approved_account'] ?? ''));
  $csrfToken = $_POST['csrf_token'] ?? '';

  if (!$transactionId || !hash_equals($_SESSION['withdrawal_admin_csrf'], $csrfToken)) {
    $msg = "Invalid withdrawal approval request.";
    $msgType = "danger";
  } elseif ($approvedAddress === '' || strlen($approvedAddress) > 200) {
    $msg = "Enter a valid approved destination address (maximum 200 characters).";
    $msgType = "danger";
  } else {
    $link->begin_transaction();
    try {
      $select = $link->prepare("SELECT email, usd, mode, account, comment, status FROM btc WHERE id = ? AND type = 'Withdrawal' FOR UPDATE");
      if (!$select) {
        throw new RuntimeException("Unable to load the withdrawal request.");
      }
      $select->bind_param('i', $transactionId);
      $select->execute();
      $withdrawal = $select->get_result()->fetch_assoc();
      $select->close();

      if (!$withdrawal || $withdrawal['status'] !== 'pending') {
        throw new RuntimeException("This withdrawal is missing or has already been processed.");
      }

      $originalAddress = trim((string)$withdrawal['account']);
      $audit = json_decode((string)$withdrawal['comment'], true);
      if (!is_array($audit) || ($audit['kind'] ?? '') !== 'admin_withdrawal_address_audit') {
        $audit = [
          'kind' => 'admin_withdrawal_address_audit',
          'original_destination' => $originalAddress,
          'previous_comment' => (string)$withdrawal['comment'],
          'changes' => [],
        ];
      }
      $audit['changes'][] = [
        'approved_destination' => $approvedAddress,
        'approved_by_admin_id' => (int)$_SESSION['uid'],
        'approved_at' => date('Y-m-d H:i:s'),
      ];
      $auditJson = json_encode($audit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($auditJson === false) {
        throw new RuntimeException("Unable to record the address audit history.");
      }

      $approve = $link->prepare("UPDATE btc SET account = ?, comment = ?, status = 'approved' WHERE id = ? AND type = 'Withdrawal' AND status = 'pending'");
      if (!$approve) {
        throw new RuntimeException("Unable to prepare withdrawal approval.");
      }
      $approve->bind_param('ssi', $approvedAddress, $auditJson, $transactionId);
      $approve->execute();
      if ($approve->affected_rows !== 1) {
        $approve->close();
        throw new RuntimeException("The withdrawal could not be approved.");
      }
      $approve->close();
      if (!$link->commit()) {
        throw new RuntimeException("The withdrawal approval transaction could not be committed.");
      }
      $msg = "Withdrawal approved. Original and approved destination addresses were recorded.";

      try {
        require_once 'PHPMailer/PHPMailer.php';
        require_once 'PHPMailer/Exception.php';
        $userLookup = $link->prepare("SELECT username FROM users WHERE email = ? LIMIT 1");
        $username = '';
        if ($userLookup && $userLookup->bind_param('s', $withdrawal['email']) && $userLookup->execute()) {
          $userLookup->bind_result($username);
          $userLookup->fetch();
          $userLookup->close();
        }

        $mail = new PHPMailer(true);
        $mail->setFrom($emaila, $name);
        $mail->addAddress($withdrawal['email'], $username);
        $mail->Subject = "Withdrawal Request Approval";
        $mail->isHTML(true);
        $mail->Body = '<p>Dear ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . ',</p>'
          . '<p>Your withdrawal request of $' . number_format((float)$withdrawal['usd'], 2) . ' USD ('
          . htmlspecialchars($withdrawal['mode'], ENT_QUOTES, 'UTF-8') . ') was approved.</p>'
          . '<p>Approved destination: <strong>' . htmlspecialchars($approvedAddress, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        $mail->AltBody = 'Your withdrawal of $' . number_format((float)$withdrawal['usd'], 2) . ' USD was approved. Approved destination: ' . $approvedAddress;
        $mail->send();
      } catch (Throwable $mailError) {
        error_log('Withdrawal approval email failed for transaction ' . $transactionId . ': ' . $mailError->getMessage());
      }
    } catch (Throwable $error) {
      $link->rollback();
      $msg = $error->getMessage();
      $msgType = "danger";
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
 <?php if($msg != "") echo "<div class='alert alert-" . htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</div>"; ?>
         
<div class="col-md-12 col-sm-12 col-sx-12">
               <div class="table-responsive">
                     <table class="display"  id="example">



					<thead>

						<tr class="info">
						<th>Email</th>
            <th>Mode</th>
                    <th>Original destination</th>
                    <th>Approved destination</th>
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

  $addressAudit = json_decode((string)($row['comment'] ?? ''), true);
  $originalDestination = is_array($addressAudit) && ($addressAudit['kind'] ?? '') === 'admin_withdrawal_address_audit'
    ? (string)($addressAudit['original_destination'] ?? $row['account'])
    : (string)$row['account'];
  $approvedDestinations = is_array($addressAudit) && ($addressAudit['kind'] ?? '') === 'admin_withdrawal_address_audit'
    ? ($addressAudit['changes'] ?? [])
    : [];
  $lastChange = $approvedDestinations ? $approvedDestinations[count($approvedDestinations) - 1] : null;
  $lastApprovedDestination = is_array($lastChange) ? (string)($lastChange['approved_destination'] ?? $row['account']) : (string)$row['account'];

$row['status'];
   
   
if(isset($row['status']) &&  $row['status']== 'approved'){
	
	
	$sec = 'Approved &nbsp;&nbsp;<i style="background-color:green;color:#fff; font-size:20px;" class="fa  fa-check" ></i>';

}else{
$sec ='Pending &nbsp;&nbsp;<i class="fa  fa-refresh" style=" font-size:20px;color:red"></i>';

}

				  ?>

						<tr class="primary">
						<form action="withdraw.php" method="post">
						
                          <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');?></td>
                                        <td><?php echo htmlspecialchars($row['mode'], ENT_QUOTES, 'UTF-8');?></td>
                                        <td><?php echo htmlspecialchars($originalDestination, ENT_QUOTES, 'UTF-8');?></td>
                          <td>
                            <?php if ($row['status'] === 'pending'): ?>
                              <label for="approved-account-<?php echo (int)$row['id']; ?>">Address to approve</label>
                              <input id="approved-account-<?php echo (int)$row['id']; ?>" type="text" name="approved_account" value="<?php echo htmlspecialchars((string)$row['account'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="200" required style="min-width:260px;color:#222;">
                            <?php else: ?>
                              <?php echo htmlspecialchars($lastApprovedDestination, ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                          </td>
						  
						  <td style="display:none;"><input type="hidden" name="transaction_id" value="<?php echo (int)$row['id'];?>"> </td>
              <td style="display:none;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['withdrawal_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>"></td>
							
							<td style="display:none;"><input type="hidden" name="tnx" value="<?php echo $row['id'];?>"> </td>
						  
							<td>$<?php echo $row['usd'];?></td>
							<td><?php echo $sec ;?></td>
              
        <td>
                          <?php echo htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8'); ?><br>
          <input type="datetime-local" name="transaction_date" value="<?php echo htmlspecialchars(date('Y-m-d\\TH:i', strtotime($row['date']))); ?>" required style="max-width:190px; color:#222;">
          <button class="btn btn-default btn-xs" type="submit" name="backdate">Save date</button>
        </td>
			  
								<td><?php if ($row['status'] === 'pending'): ?><button class="btn btn-primary" type="submit" name="approve"><span class="glyphicon glyphicon-check"> Approve with this address</span></button><?php else: ?>&mdash;<?php endif; ?></td>
							
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

