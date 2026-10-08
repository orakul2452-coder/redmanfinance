
<?php

session_start();


include "../../conn.php";

$msg = "";
use PHPMailer\PHPMailer\PHPMailer;

if(isset($_SESSION['uid'])){
	



}
else{


	header("location:../c2wadmin/signin.php");
}


if(isset($_POST['bank'])){



$email =$link->real_escape_string( $_POST['email']);
$ewallet =$link->real_escape_string( $_POST['ewallet']);
$bwallet =$link->real_escape_string( $_POST['bwallet']);
$pm =$link->real_escape_string($_POST['pm']);




    $sql = "UPDATE admin SET ewallet='$ewallet', bwallet='$bwallet',pm='$pm' WHERE email='$email'";



	if (mysqli_query($link, $sql)) {

  
               $msg= " Details has been successfully added";

                           } else {
                        $msg= " Details was not added ";
                         }
                         
}


if(isset($_POST['ubank'])){



$umember =$link->real_escape_string( $_POST['member']);
$udeposit =$link->real_escape_string( $_POST['deposit']);
$uwithdraw =$link->real_escape_string( $_POST['withdraw']);




    $sql = "UPDATE admin SET member='$umember', deposit='$udeposit',withdraw='$uwithdraw'";



	if (mysqli_query($link, $sql)) {

  
               $msg= " Details has been successfully Updated";

                           } else {
                        $msg= " Details was not Updated ";
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

          <h4 align="center"><i class="fa fa-plus"></i> COUNTER MANAGEMENT</h4>
</br>


        
         

 
          <hr></hr>
          
        
          
            <div class="box-header with-border">
            
            <?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
          </br>
          
          
            <?php   
         $sql1= "SELECT * FROM admin";
         $result1 = mysqli_query($link,$sql1);
         if(mysqli_num_rows($result1) > 0){
         $row = mysqli_fetch_assoc($result1);
       
           if(isset($row['member'])){
         $memadmin = $row['member'];
       }else{
         $memadmin="";
       }
       
       if(isset($row['deposit'])){
         $depadmin = $row['deposit'];
       }else{
         $depadmin="";
       }
       if(isset($row['withdraw'])){
         $wthadmin = $row['withdraw'];
       }else{
         $wthadmin="";
       }
       
      
      }
          ?>

     <form class="form-horizontal" action="addcounter.php" method="POST" >

           <legend>Update Counter</legend>
		   
		   
		 

     <div class="form-group">
         <label>Member Counter</label>
        <input type="text" name="member" value="<?php echo $memadmin ;?>" placeholder="Member Counter"  class="form-control">
        </div>

       <div class="form-group">
            <label>Deposited Counter</label>
        <input type="text"  name="deposit" value="<?php echo $depadmin ;?>" placeholder="Deposited Counter"  class="form-control">
      </div>

      <div class="form-group">
           <label>Withdrawal Counter</label>
        <input type="text"   name="withdraw" value="<?php echo $wthadmin ;?>" placeholder="Withdrawal Counter"   class="form-control">
      </div>

     
   
	  
	  <button style="" type="submit" class="btn btn-primary" name="ubank" >Upgrade Details </button>
	  


    </form>
    </div>
   </div>

   </div>
  </div>
  </section>
</div>

