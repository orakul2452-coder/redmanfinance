<?php

session_start();


include "../../conn.php";
include "../../config.php";
include "header.php";
$msg = "";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if(isset($_SESSION['uid'])){
	



}
else{


	header("location:../c2wadmin/signin.php");
}



  


function sendEmail($emails, $name, $emaila, $subject, $message){
  try {
    redman_email_send_with_smtp_fallback(static function () use ($emails, $name, $emaila, $subject, $message) {
      $mail = new PHPMailer\PHPMailer\PHPMailer(true);
      $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: ($emaila ?: (getenv('SMTP_USERNAME') ?: '')), $name ?: (getenv('MAIL_FROM_NAME') ?: 'Redman Finance'));
      $mail->addAddress($emails);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = '<div style="background:#f5f7f8;font-family:sans-serif"><div style="background:#fff;max-width:600px;margin:0 auto;padding:30px"><h1>' . htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1><p style="line-height:1.6">' . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p></div></div>';
      $mail->AltBody = $message;
      return $mail;
    }, 'Admin bulk email');
    return true;
  } catch (Throwable $mailError) {
    error_log('Admin bulk email failed: ' . $mailError->getMessage());
    return false;
  }
}


  

if(isset($_POST['submit'])){




 
$subject = $link->real_escape_string($_POST['subject']);

$message = $link->real_escape_string($_POST['message']);



     $sqlw= "SELECT * FROM users";
  require_once __DIR__ . '/../../email_notifications.php';
  $resultw = mysqli_query($link,$sqlw);
  if(mysqli_num_rows($resultw) > 0){
    $sentCount = 0;
    $failedCount = 0;
	  while($row2 = mysqli_fetch_assoc($resultw)){ 
	      $uemail = $row2['email']; 
        if (filter_var($uemail, FILTER_VALIDATE_EMAIL) && sendEmail($uemail, $name, $emaila, $subject, $message)) {
            $sentCount++;
        } else {
            $failedCount++;
        }
	      
	  }
    $msg = "Bulk email completed: {$sentCount} sent, {$failedCount} failed.";



								
  }             			      

        
    }







    ?>




  <div class="content-wrapper">
  


  <!-- Main content -->
  <section class="content">


<div style="width:100%">
          <div class="box box-default">
            <div class="box-header with-border">

	<div class="row">


		 <h2 class="text-center">MAIL MANAGEMENT</h2>
		  </br>

</br>


        
         

 
          <hr></hr>
          
        
          
            <div class="box-header with-border">
            
            <?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
          </br>

     <form class="form-horizontal" action="sendmail.php" method="POST" >

           <legend>Send Mail To All Users  </legend>
		   
		  
 <div class="form-group" style="margin-right: 0;margin-left: 0;">
       
        
         <div class="form-group" style="margin-right: 0;margin-left: 0;">
        <input type="text" name="subject" placeholder="Email Subject"  class="form-control">
        </div>
        
    
       
        
        <div class="form-group" style="margin-right: 0;margin-left: 0;">
        <textarea id="message" name="message" placeholder="Write your mail here"  class="form-control"></textarea>
        </div>
         <script src="//cdn.ckeditor.com/4.16.0/basic/ckeditor.js"></script>
                <script data-sample="1">
			CKEDITOR.replace( 'message', {
				height: 150
            } );
            
		</script>
      
</div>
	

      
      
	  
	  <button style="" type="submit" class="btn btn-primary" name="submit" > <i class="fa fa-send"></i>&nbsp; Send Mail </button>


    </form>


    </div>
   </div>

   </div>
  </div>
  </section>
</div>

