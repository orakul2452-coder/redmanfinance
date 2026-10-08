
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




if(isset($_POST['ubank'])){



$name =$link->real_escape_string( $_POST['name']);
$address =$link->real_escape_string( $_POST['address']);

    $sql = "INSERT INTO wallet (name,address) VALUES ('$name','$address')";



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

          <h4 align="center"><i class="fa fa-plus"></i> New Cryptocurrency MANAGEMENT</h4>
</br>


        
         

 
          <hr></hr>
          
        
          
            <div class="box-header with-border">
            
            <?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
          </br>
          
          
          

     <form class="form-horizontal" method="POST" enctype="multipart/form-data" >

           <legend>Add New Cryptocurrency</legend>
		   
		   


     <div class="form-group">
         <label> Name</label>
        <input type="text" name="name" value="" placeholder="Cryptocurrency Name"  class="form-control">
        </div>
        <div class="form-group">
         <label> Wallet Address</label>
        <input type="text" name="address" value="" placeholder="Cryptocurrency Name"  class="form-control">
        </div>

        
       

      

     
   
	  
	  <button style="" type="submit" class="btn btn-primary" name="ubank" >Update </button>
	  


    </form>
    </div>
   </div>

   </div>
  </div>
  </section>
</div>

