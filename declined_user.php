<?php
// declined_user.php
require 'functions/dbconn.php';
session_start();

// Assume account_ID is stored in session on login
$accountId = $_SESSION['loggedInUserID'] ?? null;
if (!$accountId) {
    echo "No account ID found.";
    exit;
}

// Fetch from declined_accounts
$stmt = $conn->prepare("SELECT full_name, birthdate, reason, time_declined FROM declined_accounts WHERE account_ID = ?");
$stmt->bind_param("s", $accountId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    $fullName = '';
    $birthdate = '';
    $reason = '';
} else {
    $row = $res->fetch_assoc();
    $fullName = $row['full_name'];
    $birthdate = $row['birthdate'];
    $reason = $row['reason'];
    $timeDeclined = $row['time_declined'];
}
$stmt->close();

// Optional: format birthdate, time_declined
function formatDate($dateStr) {
    if (!$dateStr) return '';
    $d = DateTime::createFromFormat('Y-m-d', $dateStr) ?: new DateTime($dateStr);
    return $d->format('F d, Y');
}
function formatDateTime($dtStr) {
    if (!$dtStr) return '';
    $d = new DateTime($dtStr);
    return $d->format('F d, Y - h:i A');
}
$birthFmt = formatDate($birthdate);
$declinedAt = formatDateTime($timeDeclined ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Declined</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="declined_user.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="page-wrapper">
        <div class="declined-container">
            <!-- Animated Icon -->
            <div class="icon-wrapper">
                <div class="icon-stack">
                    <span class="material-symbols-outlined declined-icon-bg">circle</span>
                    <span class="material-symbols-outlined declined-icon">cancel</span>
                </div>
            </div>
    
            <!-- Content -->
            <h1 class="declined-title">ACCOUNT DECLINED</h1>

            <!-- Details Card -->
            <div class="details-card">
                <h6>Account Details</h6>
                <table class="table table-borderless w-auto mx-auto">
                    <tbody>
                        <?php if (!empty($fullName)): ?>
                        <tr>
                            <th class="text-start pe-3">Account Name:</th>
                            <td class="text-start"><?php echo htmlspecialchars($fullName) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($accountId)): ?>
                        <tr>
                            <th class="text-start pe-3">Account ID:</th>
                            <td class="text-start"><?php echo htmlspecialchars($accountId) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($declinedAt)): ?>
                        <tr>
                            <th class="text-start pe-3">Declined On:</th>
                            <td class="text-start"><?php echo htmlspecialchars($declinedAt) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Reason Box -->
            <?php if (!empty($reason)): ?>
            <div class="reason-box">
                <h6>Reason for Decline</h6>
                <p><?php echo nl2br(htmlspecialchars($reason)) ?></p>
            </div>
            <?php endif; ?>

            <!-- Message -->
            <p class="declined-message">
                Unfortunately, your account request has been declined.<br>
                If you believe this is an error or wish to appeal, please contact our support team with your account details.<br>
                We appreciate your understanding.
            </p>

            <!-- Back Home Button -->
            <a href="index.php" class="btn btn-back-home">
                <span class="material-symbols-outlined">home</span>
                BACK TO HOME
            </a>
        </div>
    </div>
</body>
</html>