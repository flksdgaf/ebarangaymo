<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - eBarangayMo</title>
    <!-- CUSTOM CSS -->
    <link rel="stylesheet" href="adminpanel.css">
    <link rel="stylesheet" href="sidebar.css">
    <!-- GOOGLE FONTS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FULL CALENDAR -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous" defer></script>
    <!-- FONT AWESOME -->
    <script src="https://kit.fontawesome.com/e30afd7a6b.js" crossorigin="anonymous"></script>

</head>
<body>
    <nav class="navbar navbar-expand-lg border-bottom px-3 py-2 main-content admin-top-bar">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 topbar-title">
                <img src="images/good_governance_logo.png" alt="Good Governance Logo" style="width: 40px; height: 40px; object-fit: contain;">
                <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain;">
                <span class="fw-bold ms-1 topbar-text">
                    Barangay Magang
                </span>
            </div>
            <div class="d-none d-md-block flex-grow-1"></div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <!-- Text for large screens -->
                    <span class="d-none d-md-inline">Barangay Captain</span>
                    <!-- Icon for smaller screens -->
                    <span class="d-md-none icon"><i class="fas fa-user"></i></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                    <li><a class="dropdown-item" href="#">Profile</a></li>
                    <li><a class="dropdown-item text-danger" href="#">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
