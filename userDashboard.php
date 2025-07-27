<?php 
include 'functions/dbconn.php'; 

$res = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$slides = $res->fetch_all(MYSQLI_ASSOC);

$sql = "
  SELECT transaction_id,
         full_name,
         request_type,
         DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS formatted_date
    FROM view_request
   WHERE account_id = ?
     AND document_status <> 'Released'
   ORDER BY created_at ASC
   LIMIT 3
";
$st = $conn->prepare($sql);
$st->bind_param('i', $userId);
$st->execute();
$result = $st->get_result();
?>

<style>
.carousel-inner img {
    height: 640px;
    object-fit: contain;
}
  
.carousel-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
}
  
.carousel-blur-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    filter: blur(15px);
    transition: background-image 0.5s ease-in-out;
    z-index: 1;
    transform: scale(1.1);
  
}

.carousel-container {
    position: relative;
    z-index: 2;
}

.carousel-control-prev, 
.carousel-control-next {
    top: 60%;
    transform: translateY(-75%);
}

.gradient-text {
    background: linear-gradient(to bottom, #2A9245, #0D2C15);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
</style>

<title>eBarangay Mo | Dashboard</title>
<!-- CAROUSEL SECTION -->
<div class="row g-3 py-4 px-3">
    <div class="col-md-12">

        
        <div class="card p-4 shadow-sm mb-3">
            <h3 class="gradient-text fw-bold mb-1">ANNOUNCEMENTS</h3>
            <div class="d-flex justify-content-between align-items-center mb-3"></div>

            <div class="carousel-wrapper">
                <div class="carousel-blur-bg"></div> 

                <div class="carousel-container">
                    <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <?php foreach($slides as $i=>$s): ?>
                        <button type="button"
                                data-bs-target="#carouselExampleIndicators"
                                data-bs-slide-to="<?=$i?>"
                                class="<?=$i===0?'active':''?>"
                                aria-current="<?=$i===0?'true':''?>"
                                aria-label="Slide <?=($i+1)?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="carousel-inner">
                        <?php foreach($slides as $i=>$s): ?>
                        <div class="carousel-item <?=$i===0?'active':''?>">
                            <img src="announcements/<?=htmlspecialchars($s['image_file'])?>"
                                class="d-block w-100 carousel-image"
                                alt="<?=htmlspecialchars($s['title'])?>">
                            <!-- <div class="carousel-caption d-none d-md-block">
                            <h5><?=htmlspecialchars($s['title'])?></h5>
                            </div> -->
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button"
                            data-bs-target="#carouselExampleIndicators"
                            data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button"
                            data-bs-target="#carouselExampleIndicators"
                            data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="gradient-text fw-bold mb-0">MY RECENT REQUESTS</h4>
            </div>
            <div class="table-responsive" style="height:180px; overflow-y:auto;">
            <table class="table align-middle text-start table-hover">
                <thead class="table-light">
                <tr>
                    <th>Transaction No.</th>
                    <th>Name</th>
                    <th>Request</th>
                    <th>Date Created</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr style="cursor:pointer"
                        onclick="window.location.href='?page=userRequest&pagination=<?= $page ?>&transaction_id=<?= $row['transaction_id'] ?>'">
                        <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['request_type']) ?></td>
                        <td><?= $row['formatted_date'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">No requests found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>                     
    </div>
</div>

<script src="js/carousel.js"></script>