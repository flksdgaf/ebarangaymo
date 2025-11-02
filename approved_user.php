<?php
// approved_user.php
require 'functions/dbconn.php';
session_start();

// Assume account_ID is stored in session on login
$accountId = $_SESSION['loggedInUserID'] ?? null;
if (!$accountId) {
    echo "No account ID found.";
    exit;
}

$fullName = '';
$birthdate = '';
$foundPurok = null;

// 1. Search the 6 purok tables for the record
for ($i = 1; $i <= 6; $i++) {
    $tbl = "purok{$i}_rbi";
    $sql = "SELECT full_name, birthdate FROM `$tbl` WHERE account_ID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        continue;
    }
    $stmt->bind_param("s", $accountId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $fullName = $row['full_name'];
        $birthdate = $row['birthdate'];
        $foundPurok = $i;
        $stmt->close();
        break;
    }
    $stmt->close();
}

// Optional: format birthdate
function formatDate($dateStr) {
    if (!$dateStr) return '';
    $d = DateTime::createFromFormat('Y-m-d', $dateStr) ?: new DateTime($dateStr);
    return $d->format('F d, Y');
}
$birthFmt = formatDate($birthdate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Approved</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="approved_user.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="page-wrapper">
        <div class="approval-container">
            <!-- Animated Icon -->
            <div class="icon-wrapper">
                <div class="icon-stack">
                    <span class="material-symbols-outlined approval-icon-bg">circle</span>
                    <span class="material-symbols-outlined approval-icon">check_circle</span>
                </div>
            </div>
    
            <!-- Content -->
            <h1 class="approval-title">ACCOUNT APPROVED</h1>

            <!-- Details Card -->
            <div class="details-card">
                <h6>Account Details</h6>
                <table class="table table-borderless w-auto mx-auto">
                    <tbody>
                        <?php if (!empty($accountId)): ?>
                        <tr>
                            <th class="text-start pe-3">Account ID:</th>
                            <td class="text-start"><?php echo htmlspecialchars($accountId) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($fullName)): ?>
                        <tr>
                            <th class="text-start pe-3">Account Name:</th>
                            <td class="text-start"><?php echo htmlspecialchars($fullName) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Message -->
            <p class="approval-message">
                Congratulations! Your account has been approved.<br>
                You can now log in using your credentials.<br>
                Thank you for joining. You may proceed to your dashboard by clicking the button below.
            </p>

            <!-- Continue Button -->
            <form id="continueForm" method="POST" action="functions/update_role_to_resident.php" class="d-inline">
                <button type="submit" class="btn btn-continue">
                    <span class="material-symbols-outlined">login</span>
                    CONTINUE TO MY ACCOUNT
                </button>
            </form>
        </div>
    </div>
</body>
</html>