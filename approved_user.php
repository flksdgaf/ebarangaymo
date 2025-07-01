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
    // Note: avoid SQL injection by validating table names in code, not via user input.
    $sql = "SELECT full_name, birthdate FROM `$tbl` WHERE account_ID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Prepare failed; skip this table
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
    <link rel="stylesheet" href="approved_user.css">
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
                src: "https://lottie.host/1810df78-2585-4229-8aad-a66155581d90/GlQyyA7IfH.lottie",
            });
            </script>
    
            <!-- Dynamic Texts -->
            <h1 class="mb-3">ACCOUNT APPROVED</h1>

            <!-- Wrap in a flex container that centers it horizontally -->
            <div class="d-flex justify-content-center">
                <div class="p-4">
                    <!-- Heading -->
                    <h5 class="text-center mb-3">Account Details</h5>
                    <!-- Borderless table with auto width, centered -->
                    <table class="table table-borderless bg-transparent w-auto mx-auto mb-0">
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
            </div>

            <!-- Message -->
            <p class="mb-4">
                Congratulations! Your account has been approved.<br>
                You can now log in using your credentials.<br>
                Thank you for joining. You may proceed to your dashboard by clicking the button below.
            </p>

            <form id="continueForm" method="POST" action="functions/update_role_to_resident.php" class="d-inline">
                <button type="submit" class="btn btn-light btn-home">CONTINUE TO MY ACCOUNT</button>
            </form>

        </div>
    </div>

</body>
</html>
