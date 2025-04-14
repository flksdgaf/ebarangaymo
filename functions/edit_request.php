<?php
// edit_request.php

// Include your database connection
require_once 'functions/dbconn.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the transaction ID from the URL
    $transaction_id = $_GET['id'];

    // Get the updated values from the form
    $payment_status = $_POST['payment_status'];
    $document_status = $_POST['document_status'];

    // Prepare the SQL query to update the database
    $sql = "UPDATE barangay_id_requests SET payment_status = ?, document_status = ? WHERE transaction_id = ?";

    // Prepare the statement
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameters
        $stmt->bind_param("ssi", $payment_status, $document_status, $transaction_id);

        // Execute the query
        if ($stmt->execute()) {
            // If the update is successful, redirect or show a success message
            echo "Record updated successfully.";
            header("Location: adminrequest.php");  // Redirect to the requests page
            exit;
        } else {
            // Handle failure
            echo "Error updating record: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
}

// Close the database connection
$conn->close();
?>
