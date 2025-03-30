<?php
// Define database connection details directly
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "ebarangaymo_db";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
