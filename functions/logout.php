<?php

session_unset(); // Unset all session variables
session_destroy(); // Destroy the session

// Redirect to the login page or home page
header("Location: ../signin.php"); // Adjust the path to your login page
exit();

?>

<!-- // logout.php
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit; -->
