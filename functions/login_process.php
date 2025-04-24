<?php
// functions/login_process.php
session_start();
include 'dbconn.php';
$admin_roles = ['Barangay Captain', 'Barangay Secretary', 'Barangay Kagawad', 'Barangay Bookkeeper'];

if(isset($_POST['username']) && isset($_POST['password'])) {
    // Sanitize and validate the input
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if($username !== '' && $password !== '') {
        // Prepare the statement to prevent SQL injection
        $query = "SELECT * FROM user_accounts WHERE username = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        if(!$stmt) { 
            $_SESSION['login_error'] = "An internal error occurred.";
            header("Location: ../signin.php");
            exit();
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            // Verify the password (assumes password is hashed using password_hash())
            if(password_verify($password, $row['password'])) {
                // Set session variables
                $_SESSION['auth'] = true;
                $_SESSION['loggedInUserRole'] = $row['role'];
                $_SESSION['loggedInUserID'] = $row['account_id']; // assuming 'id' is the account id

                // Redirect based on role (adjust according to your application)
                if ($row['role'] === 'SuperAdmin') {
                    header("Location: ../superAdminPanel.php");
                } elseif (in_array($row['role'], $admin_roles)) {
                    header("Location: ../adminPanel.php");
                } elseif($row['role'] === 'Resident') {
                    header("Location: ../userPanel.php");
                }
                exit();
            } else {
                // Password does not match
                $_SESSION['login_error'] = "Password does not match";
                header("Location: ../signin.php");
                exit();
            }
        } else {
            // Username not found
            $_SESSION['login_error'] = "Username not found";
            header("Location: ../signin.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "All fields are mandatory";
        header("Location: ../signin.php");
        exit();
    }
} else {
    header("Location: ../signin.php");
    exit();
}
?>
