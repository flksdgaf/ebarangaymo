<?php
require_once 'functions/dbconn.php';

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();

$res = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $res->fetch_all(MYSQLI_ASSOC);

// ABOUT US
$about = $conn->query("SELECT title, background_image FROM about_banner WHERE id=1")->fetch_assoc();
$ebarangay = $conn->query("SELECT first_image, second_image, third_image FROM about_ebarangaymo WHERE id = 1")->fetch_assoc();
$officials = $conn->query("SELECT * FROM about_barangay_officials WHERE id = 1")->fetch_assoc();
$missionVision = $conn->query("SELECT mission, vision FROM about_mission_vision WHERE id = 1")->fetch_assoc();
$citizensCharter = $conn->query("SELECT description, image FROM about_citizens_charter WHERE id = 1")->fetch_assoc();
$barangayMap = $conn->query("SELECT description, image FROM about_barangay_map WHERE id = 1")->fetch_assoc();

// SERVICES
$servicesBanner = $conn->query("SELECT title, background_image FROM services_banner WHERE id=1")->fetch_assoc();
$services = $conn->query("SELECT id, icon, title, description, button_color FROM services_list ORDER BY created_at ASC");

// TRANSPARENCY
$transparency = $conn->query("SELECT title, background_image FROM transparency_banner WHERE id = 1")->fetch_assoc(); 
$transparencyContent = $conn->query("SELECT image, description FROM transparency_content WHERE id = 1")->fetch_assoc();

?>


<title>eBarangay Mo | Website Configurations</title>

<div class="container-fluid p-3">
  <div class="accordion" id="adminAccordion">

    <div class="accordion-item">
      <h2 class="accordion-header" id="headingHome">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHome" aria-expanded="false" aria-controls="collapseHome">
          Home Page
        </button>
      </h2>
      <div id="collapseHome" class="accordion-collapse collapse" aria-labelledby="headingHome" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">

              <!-- BANNER -->
              <h6 class="text-secondary fw-bold">Banner</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th>Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Barangay Logo</td>
                      <td><img src="images/<?= $info['logo'] ?>" alt="Logo" style="height:32px;"></td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editLogoModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Barangay Name</td>
                      <td><?= $info['name'] ?></td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editTextModal" data-field="name">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Barangay Address</td>
                      <td><?= $info['address'] ?></td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editTextModal" data-field="address">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- ANNOUNCEMENT -->
              <div class="d-flex align-items-center mb-3 justify-content-between">
                <h6 class="text-secondary fw-bold mb-0">Announcements</h6>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                  <i class="bi bi-plus-lg"></i> Add New Announcement
                </button>
              </div>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Title</th>
                      <th>Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($announcements as $a): ?>
                    <tr>
                      <td><?=htmlspecialchars($a['title'])?></td>
                      <td><img src="announcements/<?=htmlspecialchars($a['image_file'])?>" style="height:32px;"></td>
                      <td class="text-end">
                        <form method="POST" action="functions/update_announcements.php" style="display:inline">
                          <input type="hidden" name="delete_id" value="<?=$a['id']?>">
                          <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this slide?')">
                            <i class="bi bi-trash"></i> Delete
                          </button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- NEWS & UPDATES -->
              <div class="d-flex align-items-center mb-3 justify-content-between">
                <h6 class="text-secondary fw-bold mb-0">News and Updates</h6>
                <button id="addNewsBtn" class="btn btn-success btn-sm">
                  <i class="bi bi-plus-lg"></i> Add News
                </button>
              </div>
              <div class="table-responsive admin-table">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Cover Photo</th>
                      <th>Date</th>
                      <th>Headline</th>
                      <th>Link</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $res = $conn->query("SELECT * FROM news_updates ORDER BY date DESC");
                      while ($row = $res->fetch_assoc()):
                    ?>
                    <tr>
                      <td><img src="news/<?=htmlspecialchars($row['cover_file'])?>" style="height:32px;"></td>
                      <td><?=date('F j, Y', strtotime($row['date']))?></td>
                      <td><?=htmlspecialchars($row['headline'])?></td>
                      <td><a href="<?=htmlspecialchars($row['link'])?>" target="_blank"><?=htmlspecialchars($row['link'])?></a></td>
                      
                      <td class="text-end">
                        <div class="d-inline-flex gap-1">
                          <button class="btn btn-success btn-sm editNewsBtn"
                                  data-id="<?=$row['id']?>"
                                  data-date="<?=$row['date']?>"
                                  data-headline="<?=htmlspecialchars($row['headline'], ENT_QUOTES)?>"
                                  data-link="<?=htmlspecialchars($row['link'],   ENT_QUOTES)?>"
                                  data-cover="<?=$row['cover_file']?>">
                            <i class="bi bi-pencil"></i> Edit
                          </button>

                          <button class="btn btn-danger btn-sm deleteNewsBtn" data-id="<?=$row['id']?>">
                            <i class="bi bi-trash"></i> Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logo Modal -->
    <div class="modal fade" id="editLogoModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <form id="logoForm" class="modal-content" method="POST" enctype="multipart/form-data" action="functions/update_barangay_info.php">
          <div class="modal-header">
            <h5 class="modal-title">Edit Barangay Logo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body text-center">
            <div class="d-flex justify-content-around align-items-center">
              <!-- Current -->
              <div>
                <div class="rounded-circle border" style="width:100px;height:100px;overflow:hidden">
                  <img id="currentLogo" src="images/<?= $info['logo'] ?>" style="width:100%;"/>
                </div>
                <p class="mt-2">Current</p>
              </div>
              <!-- New -->
              <div>
                <label for="newLogoInput" class="rounded-circle border d-block" style="width:100px;height:100px;cursor:pointer;overflow:hidden">
                  <img id="newLogoPreview" src="" style="width:100%;display:none"/>
                  <div id="logoPlaceholder" class="d-flex align-items-center justify-content-center text-muted" style="width:100%;height:100%;">Choose</div>
                </label>
                <input type="file" id="newLogoInput" name="logo" accept="image/*" class="d-none" required>
                <p class="mt-2">New</p>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Logo</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Text Modal -->
    <div class="modal fade" id="editTextModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <form id="textForm" class="modal-content" method="POST" action="functions/update_barangay_info.php">
          <div class="modal-header">
            <h5 class="modal-title"><span id="textModalTitle"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="field" id="textField">
            <div class="mb-3">
              <label class="form-label" id="textModalLabel"></label>
              <input type="text" name="value" id="textModalInput" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="POST" action="functions/update_announcements.php" enctype="multipart/form-data" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">New Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input name="title" class="form-control" required>
            </div>

            <!-- inside Add Modal’s .modal-body -->
            <div class="mb-3 text-center">
              <img id="previewImg"
                  src="#"
                  class="img-fluid mb-2"
                  style="max-height:200px; display:none;"
                  alt="Preview">
              <div>
                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        onclick="document.getElementById('imgInput').click()">
                  <i class="bi bi-image"></i> Choose Image
                </button>
                <input type="file"
                      name="image"
                      id="imgInput"
                      accept="image/*"
                      style="display:none"
                      required>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- News Modal -->
    <div class="modal fade" id="newsModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <form id="newsForm" action="functions/update_news.php" method="POST" enctype="multipart/form-data" class="modal-content">
          <input type="hidden" name="id" id="newsId" value="">
          <div class="modal-header">
            <h5 class="modal-title" id="newsModalLabel">Add / Edit News</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Cover Photo</label>
              <div class="d-flex align-items-center">
                <img id="currentCover" src="" alt="" class="me-3" style="height:64px; object-fit:cover;">
                <input type="file" name="cover" id="newsCover" accept="image/*">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Date</label>
              <input type="date" name="date" id="newsDate" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Headline</label>
              <input type="text" name="headline" id="newsHeadline" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Link</label>
              <input type="url" name="link" id="newsLink" class="form-control" placeholder="https://…" required>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="submit">Save</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    // Logo
    document.getElementById('newLogoInput').addEventListener('change', function(e){
      const file = e.target.files[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      document.getElementById('newLogoPreview').src = url;
      document.getElementById('newLogoPreview').style.display = 'block';
      document.getElementById('logoPlaceholder').style.display = 'none';
    });
    
    // Text
    const editTextModal = document.getElementById('editTextModal');
    editTextModal.addEventListener('show.bs.modal', e => {
      const btn    = e.relatedTarget;
      const field  = btn.getAttribute('data-field');  // “name” or “address”
      const title  = field==='name' ? 'Edit Barangay Name' : 'Edit Barangay Address';
      const label  = field==='name' ? 'Barangay Name' : 'Barangay Address';
      const val    = <?= json_encode($info) ?>[field];

      document.getElementById('textModalTitle').textContent = title;
      document.getElementById('textModalLabel').textContent = label;
      document.getElementById('textField').value = field;
      document.getElementById('textModalInput').value = val;
    });

    // show preview
    document.getElementById('imgInput').onchange = e => {
      const [file] = e.target.files;
      if (file) {
        const img = document.getElementById('previewImg');
        img.src = URL.createObjectURL(file);
        img.style.display = 'block';
      }
    };

    document.addEventListener('DOMContentLoaded',()=>{
    const newsModal = new bootstrap.Modal(document.getElementById('newsModal'));
    const form      = document.getElementById('newsForm');

    // “Add News”:
    document.getElementById('addNewsBtn').addEventListener('click',()=>{
      form.reset();
      document.getElementById('newsId').value = '';
      document.getElementById('currentCover').src = '';
      document.getElementById('newsModalLabel').textContent = 'Add News';
      newsModal.show();
    });

    // “Edit” buttons:
    document.querySelectorAll('.editNewsBtn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        document.getElementById('newsModalLabel').textContent = 'Edit News';
        document.getElementById('newsId').value       = btn.dataset.id;
        document.getElementById('newsDate').value     = btn.dataset.date;
        document.getElementById('newsHeadline').value = btn.dataset.headline;
        document.getElementById('newsLink').value     = btn.dataset.link;
        document.getElementById('currentCover').src   = `images/${btn.dataset.cover}`;
        newsModal.show();
      });
    });

    // “Delete” buttons:
    document.querySelectorAll('.deleteNewsBtn').forEach(btn=>{
      btn.addEventListener('click',async()=>{
        if(!confirm('Delete this news item?')) return;
        const form = new URLSearchParams({ id: btn.dataset.id, action: 'delete' });
        await fetch('functions/news_update.php',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: form
        });
        location.reload();
      });
    });
  });

    </script>

    <!-- ABOUT US -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingAbout">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAbout" aria-expanded="false" aria-controls="collapseAbout">
          About Page
        </button>
      </h2>
      <div id="collapseAbout" class="accordion-collapse collapse" aria-labelledby="headingAbout" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">


            <!-- BANNER -->
            <h6 class="text-secondary fw-bold">Banner</h6>
            <div class="table-responsive admin-table mb-4">
              <table class="table table-hover align-middle text-start">
                <thead class="table-light">
                  <tr>
                    <th>Properties</th>
                    <th>Current</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Title</td>
                    <td><?= htmlspecialchars($about['title']) ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editAboutBannerTitleModal">
                        <i class="bi bi-pencil"></i> Edit
                      </button>
                    </td>
                  </tr>
                  <tr>
                    <td>Background Image</td>
                    <td>
                      <?php if (!empty($about['background_image']) && file_exists("images/" . $about['background_image'])): ?>
                        <img src="images/<?= htmlspecialchars($about['background_image']) ?>?v=<?= time() ?>" style="height:32px;">
                      <?php else: ?>
                        <span class="text-muted">No image</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editAboutBannerImageModal">
                        <i class="bi bi-pencil"></i> Edit
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!--Banner Title Modal -->
            <div class="modal fade" id="editAboutBannerTitleModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="functions/update_about_banner.php" class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit About Banner Title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label class="form-label">New Title</label>
                      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($about['title']) ?>" required>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Banner Background Image Modal -->
            <div class="modal fade" id="editAboutBannerImageModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="functions/update_about_banner.php" enctype="multipart/form-data" class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit About Banner Background Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body text-center">
                    <?php if (!empty($about['background_image']) && file_exists("images/" . $about['background_image'])): ?>
                      <img src="images/<?= htmlspecialchars($about['background_image']) ?>?v=<?= time() ?>" class="img-fluid mb-3" style="max-height: 150px;">
                    <?php else: ?>
                      <p class="text-muted mb-3">No image uploaded.</p>
                    <?php endif; ?>
                    <div class="mb-3">
                      <input type="file" name="background_image" accept="image/*" class="form-control" required>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                  </div>
                </form>
              </div>
            </div>


              <!-- eBARANGAY MO -->
              <h6 class="text-secondary fw-bold">eBarangay Mo</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Image</th>
                      <th>Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (['first_image', 'second_image', 'third_image'] as $label): ?>
                    <tr>
                      <td><?= ucfirst(str_replace('_', ' ', $label)) ?></td>
                      <td>
                        <?php if (!empty($ebarangay[$label]) && file_exists("images/" . $ebarangay[$label])): ?>
                          <img src="images/<?= htmlspecialchars($ebarangay[$label]) ?>?v=<?= time() ?>" style="height:32px;">
                        <?php else: ?>
                          <span class="text-muted">No image</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#edit<?= ucfirst($label) ?>Modal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- eBarangay Mo First Image Modal -->
              <div class="modal fade" id="editFirst_imageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                  <form action="functions/update_about_ebarangaymo.php" method="post" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit First Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="file" name="first_image" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" class="btn btn-success">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!--  eBarangay Mo Second Image Modal -->
              <div class="modal fade" id="editSecond_imageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                  <form action="functions/update_about_ebarangaymo.php" method="post" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Second Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="file" name="second_image" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" class="btn btn-success">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!--  eBarangay Mo Third Image Modal -->
              <div class="modal fade" id="editThird_imageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                  <form action="functions/update_about_ebarangaymo.php" method="post" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Third Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="file" name="third_image" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" class="btn btn-success">Save</button>
                    </div>
                  </form>
                </div>
              </div>


              <!-- BARANGAY OFFICIALS -->
              <h6 class="text-secondary fw-bold">Barangay Officials</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th class="text-center">Current</th> <!-- Center the middle column -->
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Description</td>
                      <td class="text-center">
                        <?= htmlspecialchars(mb_strimwidth($officials['description'], 0, 70, '...')) ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editOfficialsDescriptionModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Image</td>
                      <td class="text-center">
                        <?php if (!empty($officials['image']) && file_exists("images/" . $officials['image'])): ?>
                          <img src="images/<?= htmlspecialchars($officials['image']) ?>?v=<?= time() ?>" style="height:32px;">
                        <?php else: ?>
                          <span class="text-muted">No image</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editOfficialsImageModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!--Barangay Officials Description Modal -->
              <div class="modal fade" id="editOfficialsDescriptionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_barangay_officials.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Barangay Officials Description</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($officials['description']) ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Barangay Officials Image Modal -->
              <div class="modal fade" id="editOfficialsImageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_barangay_officials.php" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Barangay Officials Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                      <?php if (!empty($officials['image']) && file_exists("images/" . $officials['image'])): ?>
                        <img src="images/<?= htmlspecialchars($officials['image']) ?>?v=<?= time() ?>" class="img-fluid mb-3" style="max-height: 150px;">
                      <?php else: ?>
                        <p class="text-muted mb-3">No image uploaded.</p>
                      <?php endif; ?>
                      <div class="mb-3">
                        <input type="file" name="image" accept="image/*" class="form-control" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>


              <!--MISSION & VISION -->
              <h6 class="text-secondary fw-bold">Mission and Vision</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th class="text-center">Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                    <td>Mission</td>
                    <td class="text-center">
                      <?= isset($missionVision['mission']) ? htmlspecialchars(mb_strimwidth($missionVision['mission'], 0, 70, '...')) : '<span class="text-muted">No data</span>' ?>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editMissionModal">
                        <i class="bi bi-pencil"></i> Edit
                      </button>
                    </td>
                  </tr>
                  <tr>
                    <td>Vision</td>
                    <td class="text-center">
                      <?= isset($missionVision['vision']) ? htmlspecialchars(mb_strimwidth($missionVision['vision'], 0, 70, '...')) : '<span class="text-muted">No data</span>' ?>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editVisionModal">
                        <i class="bi bi-pencil"></i> Edit
                      </button>
                    </td>
                  </tr>
                  </tbody>
                </table>
              </div>

              <!-- Mission Modal -->
              <div class="modal fade" id="editMissionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_mission_vision.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Mission</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">Mission</label>
                        <textarea name="mission" class="form-control" rows="4" required><?= htmlspecialchars($missionVision['mission']) ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Vision Modal -->
              <div class="modal fade" id="editVisionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_mission_vision.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Vision</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">Vision</label>
                        <textarea name="vision" class="form-control" rows="4" required><?= htmlspecialchars($missionVision['vision']) ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>
            

              <!-- CITIZENS CHARTER -->
              <h6 class="text-secondary fw-bold">Citizen’s Charter</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th class="text-center">Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Description</td>
                      <td class="text-center">
                        <?= htmlspecialchars(mb_strimwidth($citizensCharter['description'], 0, 70, '...')) ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editCitizensCharterDescriptionModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Image</td>
                      <td class="text-center">
                        <?php if (!empty($citizensCharter['image']) && file_exists("images/" . $citizensCharter['image'])): ?>
                          <img src="images/<?= htmlspecialchars($citizensCharter['image']) ?>?v=<?= time() ?>" style="height:32px;">
                        <?php else: ?>
                          <span class="text-muted">No image</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editCitizensCharterImageModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Citizens Charter Description Modal -->
              <div class="modal fade" id="editCitizensCharterDescriptionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_citizens_charter.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Citizen’s Charter Description</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($citizensCharter['description']) ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Citizens Charter Image Modal -->
              <div class="modal fade" id="editCitizensCharterImageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_citizens_charter.php" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Citizen’s Charter Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                      <?php if (!empty($citizensCharter['image']) && file_exists("images/" . $citizensCharter['image'])): ?>
                        <img src="images/<?= htmlspecialchars($citizensCharter['image']) ?>?v=<?= time() ?>" class="img-fluid mb-3" style="max-height: 150px;">
                      <?php else: ?>
                        <p class="text-muted mb-3">No image uploaded.</p>
                      <?php endif; ?>
                      <div class="mb-3">
                        <input type="file" name="image" accept="image/*" class="form-control" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>


              <!-- BARANGAY MAP -->
              <h6 class="text-secondary fw-bold">Barangay Map</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th class="text-center">Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Description</td>
                      <td class="text-center">
                        <?= htmlspecialchars(mb_strimwidth($barangayMap['description'], 0, 70, '...')) ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editBarangayMapDescriptionModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Image</td>
                      <td class="text-center">
                        <?php if (!empty($barangayMap['image']) && file_exists("images/" . $barangayMap['image'])): ?>
                          <img src="images/<?= htmlspecialchars($barangayMap['image']) ?>?v=<?= time() ?>" style="height:32px;">
                        <?php else: ?>
                          <span class="text-muted">No image</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editBarangayMapImageModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Barangay Map Description Modal -->
              <div class="modal fade" id="editBarangayMapDescriptionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_barangay_map.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Barangay Map Description</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($barangayMap['description']) ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Barangay Map Image Modal -->
              <div class="modal fade" id="editBarangayMapImageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_about_barangay_map.php" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Barangay Map Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                      <?php if (!empty($barangayMap['image']) && file_exists("images/" . $barangayMap['image'])): ?>
                        <img src="images/<?= htmlspecialchars($barangayMap['image']) ?>?v=<?= time() ?>" class="img-fluid mb-3" style="max-height: 150px;">
                      <?php else: ?>
                        <p class="text-muted mb-3">No image uploaded.</p>
                      <?php endif; ?>
                      <div class="mb-3">
                        <input type="file" name="image" accept="image/*" class="form-control" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>


            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SERVICES -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingServices">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseServices" aria-expanded="false" aria-controls="collapseServices">
          Services
        </button>
      </h2>
      <div id="collapseServices" class="accordion-collapse collapse" aria-labelledby="headingServices" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">
              
              <!-- Services Banner -->
              <h6 class="text-secondary fw-bold">Services Banner</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th>Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Title</td>
                      <td><?= htmlspecialchars($servicesBanner['title']) ?></td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editServicesBannerTitleModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Background Image</td>
                      <td>
                        <?php if (!empty($servicesBanner['background_image']) && file_exists("images/" . $servicesBanner['background_image'])): ?>
                          <img src="images/<?= htmlspecialchars($servicesBanner['background_image']) ?>?v=<?= time() ?>" style="height:32px;">
                        <?php else: ?>
                          <span class="text-muted">No image</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editServicesBannerImageModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Services Banner Title Modal -->
              <div class="modal fade" id="editServicesBannerTitleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_services_banner.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Services Banner Title</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">New Title</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($servicesBanner['title']) ?>" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Services Banner Background Image Modal -->
              <div class="modal fade" id="editServicesBannerImageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_services_banner.php" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Services Banner Background Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                      <?php if (!empty($servicesBanner['background_image']) && file_exists("images/" . $servicesBanner['background_image'])): ?>
                        <img src="images/<?= htmlspecialchars($servicesBanner['background_image']) ?>?v=<?= time() ?>" class="img-fluid mb-3" style="max-height: 150px;">
                      <?php else: ?>
                        <p class="text-muted mb-3">No image uploaded.</p>
                      <?php endif; ?>
                      <div class="mb-3">
                        <input type="file" name="background_image" accept="image/*" class="form-control" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Barangay Services List -->
              <h6 class="text-secondary fw-bold d-flex justify-content-between align-items-center">
              Barangay Services
              <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                <i class="bi bi-plus-circle"></i> Add New Service
              </button>
              </h6>

              <div class="table-responsive admin-table">
              <table class="table table-hover align-middle text-start">
                <thead class="table-light">
                  <tr>
                    <th>Icon</th>
                    <th>Service Name</th>
                    <th>Description</th>
                    <th>Button Color</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                    $deleteModals = ''; // collect modals here
                    while ($row = $services->fetch_assoc()): 
                  ?>
                    <tr>
                      <td><i class="<?= htmlspecialchars($row['icon']) ?>" style="font-size: 24px;"></i></td>
                      <td><?= htmlspecialchars($row['title']) ?></td>
                      <td><?= htmlspecialchars(mb_strimwidth($row['description'], 0, 50, '...')) ?></td>
                      <td>
                        <div style="background: <?= htmlspecialchars($row['button_color']) ?>; width: 40px; height: 20px; border-radius: 4px; border: 1px solid #ccc;"></div>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteServiceModal<?= $row['id'] ?>">
                          <i class="bi bi-trash"></i> Delete
                        </button>
                      </td>
                    </tr>

                    <?php 
                    // Build the delete modal HTML and store it
                    ob_start(); ?>
                    <div class="modal fade" id="deleteServiceModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="deleteServiceLabel<?= $row['id'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="functions/update_services_list.php" class="modal-content p-3">
                          <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">

                          <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-semibold text-danger" id="deleteServiceLabel<?= $row['id'] ?>">Delete Service</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>

                          <div class="modal-body text-center">
                            <p class="mb-0 fs-6">
                              Are you sure you want to delete the service<br>
                              <strong><?= htmlspecialchars($row['title']) ?></strong>?
                            </p>
                          </div>

                          <div class="modal-footer border-0 justify-content-center">
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger px-4">
                              <i class="bi bi-trash me-1"></i> Yes
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                    <?php $deleteModals .= ob_get_clean(); ?>

                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>

            <!-- Render delete modals outside the table -->
            <?= $deleteModals ?>

              <!-- Add New Service Modal -->
              <div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_services_list.php" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Add New Service</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                      <div class="mb-3">
                        <label class="form-label">Upload Icon Image</label>
                        <input type="file" name="icon_image" accept="image/*" class="form-control" required>
                      </div>

                      <div class="mb-3">
                        <label class="form-label">Service Title</label>
                        <input type="text" name="title" class="form-control" required>
                      </div>

                      <div class="mb-3">
                        <label class="form-label">Service Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                      </div>

                      <div class="mb-3">
                        <label class="form-label">Button Color</label>
                        <select name="button_color" class="form-select" required>
                          <option value="">Select a color</option>
                          <option value="#2A9245" style="background:#2A9245; color:white;">#2A9245 (Dark Green)</option>
                          <option value="#61AD41" style="background:#61AD41; color:white;">#61AD41 (Lime Green)</option>
                          <option value="#13411F" style="background:#13411F; color:white;">#13411F (Forest Green)</option>
                        </select>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>


            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Transparency Seal -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingSeal">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeal" aria-expanded="false" aria-controls="collapseSeal">
          Transparency Seal
        </button>
      </h2>
      <div id="collapseSeal" class="accordion-collapse collapse" aria-labelledby="headingSeal" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">
              
              <!-- BANNER -->
              <h6 class="text-secondary fw-bold">Banner</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th>Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Title</td>
                      <td><?= htmlspecialchars($transparency['title']) ?></td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editTransparencyTitleModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Background Image</td>
                      <td>
                        <?php if (!empty($transparency['background_image']) && file_exists("images/" . $transparency['background_image'])): ?>
                          <img src="images/<?= htmlspecialchars($transparency['background_image']) ?>?v=<?= time() ?>" style="height:32px;">
                        <?php else: ?>
                          <span class="text-muted">No image</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editTransparencyImageModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Banner Title Modal -->
              <div class="modal fade" id="editTransparencyTitleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_transparency_banner.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Transparency Banner Title</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">New Title</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($transparency['title']) ?>" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Banner Background Image Modal -->
              <div class="modal fade" id="editTransparencyImageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_transparency_banner.php" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Transparency Banner Background Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                      <?php if (!empty($transparency['background_image']) && file_exists("images/" . $transparency['background_image'])): ?>
                        <img src="images/<?= htmlspecialchars($transparency['background_image']) ?>?v=<?= time() ?>" class="img-fluid mb-3" style="max-height: 150px;">
                      <?php else: ?>
                        <p class="text-muted mb-3">No image uploaded.</p>
                      <?php endif; ?>
                      <div class="mb-3">
                        <input type="file" name="background_image" accept="image/*" class="form-control" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- TRANSPARENCY CONTENT -->
              <h6 class="text-secondary fw-bold">Transparency Content</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th class="text-center">Current</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Description</td>
                      <td class="text-center">
                        <?= htmlspecialchars(mb_strimwidth($transparencyContent['description'], 0, 70, '...')) ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editTransparencyDescriptionModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Image</td>
                      <td class="text-center">
                        <?php if (!empty($transparencyContent['image']) && file_exists("images/" . $transparencyContent['image'])): ?>
                          <img src="images/<?= htmlspecialchars($transparencyContent['image']) ?>?v=<?= time() ?>" style="height:32px;">
                        <?php else: ?>
                          <span class="text-muted">No image</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editTransparencyImageModal">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Content Description Modal -->
              <div class="modal fade" id="editTransparencyDescriptionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_transparency_content.php" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Transparency Content Description</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($transparencyContent['description']) ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Content Image Modal -->
              <div class="modal fade" id="editTransparencyImageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form method="POST" action="functions/update_transparency_content.php" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Transparency Content Image</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                      <?php if (!empty($transparencyContent['image']) && file_exists("images/" . $transparencyContent['image'])): ?>
                        <img src="images/<?= htmlspecialchars($transparencyContent['image']) ?>?v=<?= time() ?>" class="img-fluid mb-3" style="max-height: 150px;">
                      <?php else: ?>
                        <p class="text-muted mb-3">No image uploaded.</p>
                      <?php endif; ?>
                      <div class="mb-3">
                        <input type="file" name="image" accept="image/*" class="form-control" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                  </form>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

