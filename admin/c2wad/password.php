<?php

session_start();


include "../../conn.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;
$sqlw1= "SELECT * FROM admin ORDER BY id DESC ";
                		  $resultw1 = mysqli_query($link,$sqlw1);
                		  if(mysqli_num_rows($resultw1) > 0){
                		      $row21 = mysqli_fetch_assoc($resultw1);
                		      $cpassword = $row21['password'];
                		  }

if(isset($_POST['submit'])){


$opassword =$link->real_escape_string($_POST['opassword']);
$password =$link->real_escape_string($_POST['password']);
$email =$link->real_escape_string($_POST['email']);


if ( $opassword == $cpassword){
    
    
$sql = "UPDATE admin SET password='$password'";

    
  mysqli_query($link, $sql);

  

    $msg= " Password changed successfully";
    

 }
 
 else{
    

 $msg= "Wrong Old Password! ";
}
    

}


include "header.php";


    ?>





 <div class="content-wrapper">
  


  <!-- Main content -->
  <section class="content">



   


       

<div class="col-md-12 col-sm-12 col-sx-12">
          <div class="box box-default">
            <div class="box-header with-border">

          <h4 align="center"><i class="fa fa-refresh"></i> PASSWORD UPDATE</h4>
          </br>
          	<?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
<div class="col-md-12 col-sm-12 col-sx-12">
          <!-- Widget: user widget style 1 -->
          <div class="box box-widget widget-user-2">
            <!-- Add the bg color to the header using any of the bg-* classes -->
            <div class="widget-user-header bg-white">
             

              <form class="form-horizontal" action="password.php" method="POST" >
   <div class="form-group">
<input type="password"  name="opassword"   placeholder="Old Password" class="form-control">
 
</div>          
<div class="form-group">
<input type="password"  name="password"   placeholder="New Password" class="form-control">
 
</div>



<div class="form-group">
<input type="submit"  name="submit" value="Change Password" class="btn btn-warning">
 
</div>

            </div>
          </div>
          <!-- /.widget-user -->
        </div>