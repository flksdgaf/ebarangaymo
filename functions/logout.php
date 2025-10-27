<?php
session_start();
session_unset();
session_destroy();

// Prevent caching
// header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
// header("Cache-Control: post-check=0, pre-check=0", false);
// header("Pragma: no-cache");
// header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

header("Location: ../signin.php");
exit();

// session_unset(); // Unset all session variables
// session_destroy(); // Destroy the session

// // Redirect to the login page or home page
// header("Location: ../signin.php"); // Adjust the path to your login page
// exit();
?>

<!-- // logout.php
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit; -->