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
    // No record found; fallback
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
    <link rel="stylesheet" href="declined_user.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

    <!-- Overlay second image -->
    <img src="images/bg_nothover.png" class="overlay-image" alt="Overlay Image">

    <!-- Centered content -->
    <div class="d-flex align-items-center justify-content-center vh-100">
        <div class="content">
            <!-- Lottie or GIF -->
            <canvas id="canvas" width="200" height="200"></canvas>
            <script type="module">
            import { DotLottie } from "https://cdn.jsdelivr.net/npm/@lottiefiles/dotlottie-web/+esm";
            new DotLottie({
                autoplay: true,
                loop: true,
                canvas: document.getElementById("canvas"),
                src: "https://lottie.host/6df32ebb-a562-426b-9c3f-243be5309d28/WFtQToaeNi.lottie",
            });
            </script>
    
            <!-- Dynamic Texts -->
            <h1 class="mb-3">ACCOUNT REJECTED</h1>

            <!-- Wrap in a flex container that centers it horizontally -->
            <div class="d-flex justify-content-center">
                <div class="p-4">
                    <!-- Heading -->
                    <h5 class="text-center mb-3">Account Details</h5>
                    <!-- Borderless table with auto width, centered -->
                    <table class="table table-borderless bg-transparent w-auto mx-auto mb-0">
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
                        <?php if (!empty($birthFmt)): ?>
                        <tr>
                        <th class="text-start pe-3">Birthdate:</th>
                        <td class="text-start"><?php echo htmlspecialchars($birthFmt) ?></td>
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
            </div>

            <!-- Message -->
            <p class="mb-4">
                Unfortunately, your account request has been declined based on the reason  <strong>"<?php echo nl2br(htmlspecialchars($reason)) ?>"</strong>.<br>
                If you believe this is an error or wish to appeal, please contact our support with your account details.<br>
                We appreciate your understanding.
            </p>

            <!-- Back Home Button -->
            <a href="index.php" class="btn btn-light btn-home">BACK TO HOME</a>
        </div>
    </div>

</body>
</html>
