<?php
// new_acc_signup.php

// Database configuration (replace with your actual credentials)
$servername   = "localhost";
$username_db  = "root";
$password_db  = "";
$dbname       = "magang_ebarangaymo_db";

// Create a new MySQLi connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve individual name inputs from the form.
$firstname   = trim($_POST['firstname'] ?? '');
$middlename  = trim($_POST['middlename'] ?? '');
$lastname    = trim($_POST['lastname'] ?? '');
$suffix      = trim($_POST['suffix'] ?? '');

// Construct the full name
$full_name = $firstname;
if (!empty($middlename)) {
    $full_name .= " " . $middlename;
}
$full_name .= " " . $lastname;
if (!empty($suffix)) {
    $full_name .= " " . $suffix;
}

// Retrieve individual address inputs from the form.
$province     = trim($_POST['province'] ?? '');
$municipality = trim($_POST['municipality'] ?? '');
$barangay     = trim($_POST['barangay'] ?? '');
$purok        = trim($_POST['purok'] ?? '');
$block        = trim($_POST['block'] ?? '');
$zip          = trim($_POST['zip'] ?? '');

// Construct the full address string
$full_address = "$block, $purok, $barangay, $municipality, $province, $zip";

// Other fields (make sure your form uses matching name attributes)
$birthdate = $_POST['birthdate'] ?? '';
$contact   = $_POST['contact'] ?? '';
$email     = $_POST['email'] ?? ''; // if you need email separately; otherwise, adjust as needed.
$validID   = $_POST['validID'] ?? '';
$username  = $_POST['username'] ?? '';

// Securely hash the password.
$password_plain = $_POST['password'] ?? '';
$password = password_hash($password_plain, PASSWORD_DEFAULT);

// --- Handle File Uploads ---
// Ensure your form tag includes: enctype="multipart/form-data"
// e.g. <form action="new_acc_signup.php" method="POST" enctype="multipart/form-data">
if (isset($_FILES['frontID']) && isset($_FILES['backID'])) {
    // Directories for storing the images (ensure these folders exist and are writable)
    $frontDir = "../frontID/";
    $backDir  = "../backID/";
    
    // Process front ID image upload
    $frontFile = $_FILES['frontID'];
    $frontFileName = time() . "_" . basename($frontFile["name"]);
    $frontTarget = $frontDir . $frontFileName;
    
    // Process back ID image upload
    $backFile = $_FILES['backID'];
    $backFileName = time() . "_" . basename($backFile["name"]);
    $backTarget = $backDir . $backFileName;
    
    // Move the uploaded files to your designated folders
    if (move_uploaded_file($frontFile["tmp_name"], $frontTarget) && move_uploaded_file($backFile["tmp_name"], $backTarget)) {
        // Prepare and execute the insertion query using a prepared statement
        $stmt = $conn->prepare("INSERT INTO new_acc_requests (full_name, birthdate, contact, full_address, validID, frontID, backID, username, password)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        
        // Bind parameters
        $stmt->bind_param("sssssssss", $full_name, $birthdate, $contact, $full_address, $validID, $frontFileName, $backFileName, $username, $password);
        
        if ($stmt->execute()) {
            // Record inserted successfully.
            header("Location: ../underreview.php");
            exit();
        } else {
            echo "Error inserting record: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        // Error handling for file upload issues.
        echo "There was an error uploading your ID images.";
    }
} else {
    echo "Please upload both the front and back images of your ID.";
}

$conn->close();
?>
