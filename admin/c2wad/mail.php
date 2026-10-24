<?php

session_start();


include "../../conn.php";
include "../../config.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if(isset($_SESSION['uid'])){
	



}
else{


	header("location:../c2wadmin/signin.php");
}



  





  

if(isset($_POST['submit'])){




 
$subject = $link->real_escape_string($_POST['subject']);

$message = $_POST['message'];


$emails = $link->real_escape_string($_POST['emails']);

require_once __DIR__ . '/../../email_notifications.php';
try {
  redman_email_send_with_smtp_fallback(static function () use ($emails, $emaila, $name, $subject, $message) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: ($emaila ?: (getenv('SMTP_USERNAME') ?: '')), $name ?: (getenv('MAIL_FROM_NAME') ?: 'Redman Finance'));
    $mail->addAddress($emails);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = '<div style="background:#f5f7f8;font-family:sans-serif"><div style="background:#fff;max-width:600px;margin:0 auto;padding:30px"><h1>' . htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1><p style="line-height:1.6">' . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p></div></div>';
    $mail->AltBody = $message;
    return $mail;
  }, 'Admin one-to-one email');
  $msg = 'Mail has been sent successfully!';
} catch (Throwable $mailError) {
  error_log('Admin one-to-one email failed: ' . $mailError->getMessage());
  $msg = 'Email delivery failed. Check the application logs and provider settings.';
}
        
    }






include "header.php";
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

     <form class="form-horizontal" action="mail.php" method="POST" >

           <legend>Send Mail  </legend>
		   
		  
 <div class="form-group" style="margin-right: 0;margin-left: 0;">
       
        
         <div class="form-group" style="margin-right: 0;margin-left: 0;">
        <input type="text" name="subject" placeholder="Email Subject"  class="form-control">
        </div>
        <div class="form-group" style="margin-right: 0;margin-left: 0;">
        <input type="email" name="emails" placeholder="Receipent email"  class="form-control">
        </div>
        
        
    
      
      
        
        <div class="form-group" style="margin-right: 0;margin-left: 0;">
        <textarea id="message"  name="message" placeholder="Write your mail here"  class="form-control"></textarea>
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

