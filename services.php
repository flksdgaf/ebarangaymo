<?php 
$page = 'index';
include 'includes/header.php'; 
?>

<link rel="stylesheet" href="services.css">

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
    <!-- Background image with overlay -->
    <div class="position-relative text-white text-center">
        <img src="images/about_banner.png" alt="Services Banner" class="img-fluid w-100">
        
        <!-- Overlay content -->
        <div class="position-absolute top-50 start-50 translate-middle">
            <h1 class="fw-semibold text-uppercase">Services</h1>
            <p>Home / Services</p>
        </div>
    </div>
</div>

<div class="container my-5">
    <h4 class="gradient-text fw-bold text-success">BARANGAY SERVICES</h4>
    <p class="services-desc mb-4">
        Tingnan ang iba't ibang serbisyong iniaalok ng Barangay Magang—mula sa permit at sertipiko hanggang sa mga programang pangkomunidad—upang mas mapadali at maging maginhawa ang pag‑avail ng serbisyo para sa lahat ng residente.
    </p>

    <div class="row g-4">
        <!-- BARANGAY ID -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none br-10px">
                <div class="barangay-id d-flex p-3 rounded text-white">
                <div class="me-3">
                    <i class="fas fa-id-card icon"></i>
                </div>
                <div>
                    <h5 class="barangay-id-title fw-bold mb-1">Barangay ID</h5>
                    <p class="barangay-id-desc mb-0">
                        Opisyal na identification card na inilalaan ng barangay bilang patunay ng paninirahan at pagkakakilanlan.
                    </p>
                </div>
                </div>
            </a>
        </div>

        <!-- BARANGAY CLEARANCE -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="barangay-clearance d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <i class="fas fa-file-alt icon"></i>  
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Barangay Clearance</h5>
                        <p class="barangay-clearance-desc mb-0">
                            Opisyal na dokumento na nagpapatunay na ang residente ay walang hindi pa natapos o hindi naayos na isyu sa barangay.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- CERTIFICATION -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="certification d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <i class="fas fa-certificate icon"></i> 
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Certification</h5>
                        <p class="certification-desc mb-0">
                            Opisyal na dokumento upang patunayan ang pagkakakilanlan, paninirahan, o tiyak na katayuan ng residente.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- BUSINESS PERMIT -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="business-permit d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <i class="fas fa-store icon"></i> 
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Business Permit</h5>
                        <p class="business-permit-desc mb-0">
                            Opisyal na pahintulot na ibinibigay ng barangay para makapagsagawa ng negosyo nang legal sa komunidad.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- EQUIPMENT BORROWING -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="equipment-borrowing d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <i class="fas fa-chair icon"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Equipment Borrowing</h5>
                        <p class="equipment-borrowing-desc mb-0">
                            Pagpapahiram ng kagamitan mula sa barangay para sa pansamantalang gamit, ayon sa itinakdang alituntunin at iskedyul.
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- CASH INCENTIVES -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="cash-incentives d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <i class="fas fa-money-bill cash_icon"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Cash Incentives</h5>
                        <p class="cash-incentives-desc mb-0">
                            Pagbibigay ng insentibong pera bilang pagkilala at parangal sa mga mag-aaral na may natatanging tagumpay sa akademiko.
                        </p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<?php 
    include 'includes/footer.php'; 
?> 