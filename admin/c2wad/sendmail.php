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
    
 
include_once "PHPMailer/PHPMailer.php";
    require_once 'PHPMailer/Exception.php';

//PHPMailer Object
$mail = new PHPMailer;

//From email address and name
$mail->setFrom($emaila);
   $mail->FromName = $name;

//Address to which recipient will reply
//To address and name
$mail->addAddress("$emails");

//Send HTML or Plain Text email
$mail->isHTML(true);

$mail->Subject = $subject;

$mail->Body = '<div style="background: #f5f7f8;width: 100%;height: 100%; font-family: sans-serif; font-weight: 100;" class="be_container"> 

<div style="background:#fff;max-width: 600px;margin: 0px auto;padding: 30px;"class="be_inner_containr"> <div class="be_header">



<div style="clear: both;"></div> 

<div class="be_bluebar" style="background: #fff; padding: 20px; color: #fff;margin-top: 10px;">

<h1 style="color: #000;">'.$subject.' </h1>

</div> </div> 

<div class="be_body" style="padding: 20px;"> <p style="line-height: 25px;">

'.$message.'
</p>  </div> </div>';

if($mail->send()) {
  
    $msg =  "Mail has been sent successfully!";
}
               
           else{
                $msg = "Something went wrong. Please try again later!";
            }   
    
}


  

if(isset($_POST['submit'])){




 
$subject = $link->real_escape_string($_POST['subject']);

$message = $link->real_escape_string($_POST['message']);



     $sqlw= "SELECT * FROM users";
  $resultw = mysqli_query($link,$sqlw);
  if(mysqli_num_rows($resultw) > 0){
	  while($row2 = mysqli_fetch_assoc($resultw)){ 
	      $uemail = $row2['email']; 
	      
	      sendEmail($uemail, $name, $emaila, $subject, $message);
	      
	  }



								
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

