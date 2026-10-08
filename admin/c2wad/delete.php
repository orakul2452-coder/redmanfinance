<?php
include "../../conn.php";

$email = "";
if (isset($_GET['email'])) {
    $email = $link->real_escape_string($_GET['email']);
} elseif (isset($_POST['email'])) {
    $email = $link->real_escape_string($_POST['email']);
}

if (!empty($email)) {
    mysqli_query($link, "DELETE FROM users WHERE email='$email'");
    mysqli_query($link, "DELETE FROM investment WHERE email='$email'");
    mysqli_query($link, "DELETE FROM btc WHERE email='$email'");
    echo "User record and all associated investments and transaction logs deleted successfully.";
} else {
    echo "No email provided for deletion.";
}
?>
