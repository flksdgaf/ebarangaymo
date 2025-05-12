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
    <p class="mb-4">
        Tingnan ang iba't ibang serbisyong iniaalok ng Barangay Magang—mula sa permit at sertipiko hanggang sa mga programang pangkomunidad—upang mas mapadali at maging maginhawa ang pag‑avail ng serbisyo para sa lahat ng residente.
        <!-- Access the range of services offered by Barangay Magang, including permits, certificates, and community assistance programs. 
        This page provides information to help residents conveniently avail of barangay services. -->
    </p>

    <div class="row g-4">
        <!-- BARANGAY ID -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none br-10px">
                <div class="barangay-id d-flex p-3 rounded text-white">
                <div class="me-3">
                    <i class="fas fa-id-card icon"></i>
                    <!-- <img src="images/barangay_id.png" alt="Barangay ID Icon" class="barangay-id-icon"> -->
                </div>
                <div>
                    <h5 class="barangay-id-title fw-bold mb-1">Barangay ID</h5>
                    <p class="mb-0">
                        Opisyal na identification card na inilalaan ng barangay bilang patunay ng paninirahan at pagkakakilanlan.
                        <!-- An official identification card issued by the barangay that serves as proof of residency and identity. -->
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
                        <p class="mb-0">
                            Opisyal na dokumento na nagpapatunay na ang residente ay walang hindi pa natapos o hindi naayos na isyu sa barangay.
                            <!-- An official document that certifies a resident has no pending issues in the barangay. -->
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
                        <p class="mb-0">
                            Opisyal na dokumento upang patunayan ang pagkakakilanlan, paninirahan, o tiyak na katayuan ng residente.
                            <!-- An official document issued by the barangay to confirm a resident’s identity, residency, or specific status. -->
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
                        <p class="mb-0">
                            Opisyal na pahintulot na ibinibigay ng barangay para makapagsagawa ng negosyo nang legal sa komunidad.
                            <!-- An official authorization issued by the barangay that allows a business to operate legally within the community. -->
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- KATARUNGANG PAMBARANGAY -->
        <!-- <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="katarungang-pambarangay d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/katarungang_pambarangay.png" alt="Katarungang Pambarangay Icon" class="katarungang-pambarangay-icon">
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Katarungang Pambarangay</h5>
                        <p class="mb-0">
                            Community-based na sistema ng katarungan sa barangay na tumutulong sa mapayapang pag‑aayos ng alitan.
                        </p>
                    </div>
                </div>
            </a>
        </div> -->

        <!-- ENVIRONMENTAL SERVICES -->
        <!-- <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="environmental-services d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/environmental_services.png" alt="Environmental Services Icon" class="environmental-services-icon">
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Environmental Services</h5>
                        <p class="mb-0">
                            Programa at inisyatiba para mapanatili ang kalinisan, wastong pamamahala ng basura, at proteksyon sa kapaligiran.
                        </p>
                    </div>
                </div>
            </a>
        </div> -->

        <!-- EQUIPMENT BORROWING -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="equipment-borrowing d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <i class="fas fa-chair icon"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Equipment Borrowing</h5>
                        <p class="mb-0">
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
                        <p class="mb-0">
                            Pagbibigay ng insentibong pera bilang pagkilala at parangal sa mga mag-aaral na may natatanging tagumpay sa akademiko.
                            <!-- Offering cash incentives to recognize and reward outstanding students for academic excellence. -->
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