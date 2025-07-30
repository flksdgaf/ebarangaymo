<?php
// Define database connection details directly
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "magang_ebarangaymo_db";

// Set PHP timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to match Asia/Manila
$conn->query("SET time_zone = '+08:00'");
?>

<!-- <php
// Define database connection details directly
$host = "localhost";
$user = "ebarangaymo_user";
$pass = "c?w{O]29hj@z";
$dbname = "magang_ebarangaymo_db";

// Set PHP timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to match Asia/Manila
$conn->query("SET time_zone = '+08:00'");
?> -->