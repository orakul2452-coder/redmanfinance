
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
   $pdname = "";
   $pdincrease = "";
   $pdbonus = "";
   $pdduration ="";
   $pdfroms = "";
   $pdtos = "";
   $pdtype = "";
   $vid = "";
   $id = "";

   

if(isset($_POST['package11'])){


$pname =$link->real_escape_string( $_POST['pname']);
$increase =$link->real_escape_string( $_POST['increase']);
$duration =$link->real_escape_string($_POST['duration']);
$froms =$link->real_escape_string($_POST['froms']);
$tos =$link->real_escape_string($_POST['tos']);


if(isset($_POST['mid']) && $_POST['mid'] !=''){
  $mid = $link->real_escape_string( $_POST['mid']);
  
  $sql1 = "UPDATE package1 SET pname='$pname',increase='$increase',duration='$duration',froms='$froms',tos='$tos' WHERE id='$mid'";






  if (mysqli_query($link, $sql1)) {

$msg= " Plan updated successfully!";

           } else {
        $msg= " Plan not updated ";
         }
}else{


    $sql = "INSERT INTO package1 (pname,increase,duration,froms,tos) VALUES ('$pname','$increase','$duration','$froms','$tos')";






                  if (mysqli_query($link, $sql)) {

  
               $msg= " Plan has been successfully added";

                           } else {
                        $msg= " Plan was not added ";
                         }
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

          <h4 align="center"><i class="fa fa-gears"></i> PLANS MANAGEMENT</h4>
</br>

<?php
  
  if(isset($_GET['id']) && $_GET['id'] !=''){
    $vid= "yes";
    $id = $link->real_escape_string( $_GET['id']);
$sql1= "SELECT * FROM package1 WHERE id = '$id'";
  $result1 = mysqli_query($link,$sql1);
  if(mysqli_num_rows($result1) > 0){
   $row = mysqli_fetch_assoc($result1);
   $pdname = $row['pname'];
   $pdincrease = $row['increase'];
   $pdbonus = $row['bonus'];
   $pdduration = $row['duration'];
   $pdfroms = $row['froms'];
   $pdtos = $row['tos'];
 
  }
  }

?>
        
         

 
          <hr></hr>
          
        
          
            <div class="box-header with-border">
            
            <?php if($msg != "") echo "<div style='padding:20px;background-color:#dce8f7;color:black'> $msg</div class='btn btn-success'>" ."</br></br>";  ?>
          </br>

     <form class="form-horizontal" action="addpackages.php" method="POST" >

           <legend>Plans</legend>
		   
		   
		   <input type="hidden" name="email"  value="<?php echo $_SESSION['email'];?>" class="form-control">

     <div class="form-group">
       <label> Plan Name </label>
        <input type="text" name="pname" placeholder="Plan Name" value="<?php echo $pdname;?>" class="form-control">
        </div>
        

       <div class="form-group">
       <label> Daily Percentage Increase </label>
        <input type="text"  name="increase" placeholder="Daily Percentage Increase" value="<?php echo $pdincrease;?>"  class="form-control">
      </div>

      <div class="form-group">
      <label> Plan Duration (Days) </label>
        <input type="text"  name="duration" placeholder="Plan Duration" value="<?php echo $pdduration;?>"  class="form-control">
      </div>
        
       
         <div class="form-group">
        <label> Plan Price 'FROM' </label>
        <input type="text"  name="froms" placeholder="Package Price 'FROM'"  value="<?php echo $pdfroms;?>"  class="form-control">
        </div>
        
		
        <div class="form-group">
        <label>Plan Price 'TO' </label>
        <input type="text"  name="tos" placeholder="Package Price 'TO'" value="<?php echo $pdtos;?>"  class="form-control">
        </div>
		
        <?php if($vid == ""){
          ?>
      <button style="" type="submit" class="btn btn-info" name="package11" >Add Plan</button>
	  <?php }else{?>
	  <input type="hidden" name="mid"  value="<?php echo $id;?>" class="form-control">
	  <button style="" type="submit" class="btn btn-info" name="package11" >Update Plan </button>
	  <?php }?>


    </form>




    </div>
   </div>

   </div>
  </div>
  </section>
</div>

