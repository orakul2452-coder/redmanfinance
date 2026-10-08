<?php
session_start();
include "../../conn.php";
include "../../config.php";

$msg = "";
$is_edit = false;

if(!isset($_SESSION['uid'])){
    header("location:../c2wadmin/signin.php");
    exit();
}

$id = "";
$email = "";
$pname = "";
$usd = "";
$increase = "";
$duration = "";
$profit = "";
$activate = "1";
$pdate = date('Y-m-d H:i:s');
$payday = date('Y/m/d');

// Load investment data if editing
if(isset($_GET['id']) && !empty($_GET['id'])){
    $id = $link->real_escape_string($_GET['id']);
    $sql_fetch = "SELECT * FROM investment WHERE id = '$id' LIMIT 1";
    $res_fetch = mysqli_query($link, $sql_fetch);
    if(mysqli_num_rows($res_fetch) > 0){
        $row = mysqli_fetch_assoc($res_fetch);
        $is_edit = true;
        $email = $row['email'];
        $pname = $row['pname'];
        $usd = $row['usd'];
        $increase = $row['increase'];
        $duration = $row['duration'];
        $profit = $row['profit'];
        $activate = $row['activate'];
        $pdate = $row['pdate'];
        $payday = $row['payday'];
    }
}

// Handle Form Submission
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $email = $link->real_escape_string($_POST['email']);
    $pname = $link->real_escape_string($_POST['pname']);
    $usd = (float)$_POST['usd'];
    $increase = (float)$_POST['increase'];
    $duration = (int)$_POST['duration'];
    $profit = (float)$_POST['profit'];
    $activate = (int)$_POST['activate'];
    
    if(isset($_POST['id']) && !empty($_POST['id'])){
        // Update existing investment
        $mid = $link->real_escape_string($_POST['id']);
        $sql_update = "UPDATE investment SET 
                        email = '$email', 
                        pname = '$pname', 
                        usd = '$usd', 
                        increase = '$increase', 
                        duration = '$duration', 
                        profit = '$profit', 
                        activate = '$activate' 
                       WHERE id = '$mid'";
        if(mysqli_query($link, $sql_update)){
            $msg = "Investment updated successfully!";
            $is_edit = true;
            $id = $mid;
        } else {
            $msg = "Failed to update investment: " . mysqli_error($link);
        }
    } else {
        // Create new manual investment
        $cdate = date('Y-m-d H:i:s');
        $cpayday = date('Y/m/d');
        $sql_insert = "INSERT INTO investment (email, pname, increase, bonus, duration, pdate, froms, activate, usd, profit, payday, lprofit, status)
                       VALUES ('$email', '$pname', '$increase', '0', '$duration', '$cdate', '$usd', '$activate', '$usd', '$profit', '$cpayday', '0', '')";
        if(mysqli_query($link, $sql_insert)){
            $msg = "New investment added successfully!";
        } else {
            $msg = "Failed to add investment: " . mysqli_error($link);
        }
    }
}

include 'header.php';
?>

<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title"><?php echo $is_edit ? "Edit Investment #".$id : "Add Manual Investment"; ?></h3>
            <a href="ivtpackages.php" class="btn btn-default pull-right"><i class="fa fa-arrow-left"></i> Back to Investments</a>
          </div>

          <?php if($msg != ""): ?>
            <div class="alert alert-info alert-dismissible" style="margin: 15px;">
              <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
              <?php echo $msg; ?>
            </div>
          <?php endif; ?>

          <form action="edit_investment.php<?php echo $is_edit ? '?id='.$id : ''; ?>" method="POST" class="form-horizontal" style="padding: 20px;">
            <?php if($is_edit): ?>
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="form-group">
              <label class="col-sm-3 control-label">Investor Email</label>
              <div class="col-sm-9">
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="user@example.com" required>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-3 control-label">Plan Name</label>
              <div class="col-sm-9">
                <input type="text" name="pname" class="form-control" value="<?php echo htmlspecialchars($pname); ?>" placeholder="e.g. Starter Plan" required>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-3 control-label">Invested Amount ($ USD)</label>
              <div class="col-sm-9">
                <input type="number" step="0.01" name="usd" class="form-control" value="<?php echo htmlspecialchars($usd); ?>" placeholder="1000.00" required>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-3 control-label">Daily Return (%)</label>
              <div class="col-sm-9">
                <input type="number" step="0.01" name="increase" class="form-control" value="<?php echo htmlspecialchars($increase); ?>" placeholder="1.5" required>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-3 control-label">Duration (Days)</label>
              <div class="col-sm-9">
                <input type="number" name="duration" class="form-control" value="<?php echo htmlspecialchars($duration); ?>" placeholder="30" required>
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-3 control-label">Current Profit ($ USD)</label>
              <div class="col-sm-9">
                <input type="number" step="0.01" name="profit" class="form-control" value="<?php echo htmlspecialchars($profit); ?>" placeholder="0.00">
              </div>
            </div>

            <div class="form-group">
              <label class="col-sm-3 control-label">Status</label>
              <div class="col-sm-9">
                <select name="activate" class="form-control">
                  <option value="1" <?php echo ($activate == "1" || $activate == 1) ? 'selected' : ''; ?>>Active</option>
                  <option value="0" <?php echo ($activate == "0" || $activate === 0) ? 'selected' : ''; ?>>Stopped / Completed</option>
                </select>
              </div>
            </div>

            <div class="box-footer text-right">
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo $is_edit ? "Save Changes" : "Create Investment"; ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>
</body>
</html>
