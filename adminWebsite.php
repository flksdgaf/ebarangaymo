<?php
require 'functions/dbconn.php';

$info = $conn->query("SELECT logo,name,address FROM barangay_info WHERE id=1")->fetch_assoc();

$res = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $res->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-3">
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

              <h6 class="text-secondary fw-bold">Masthead</h6>
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
              <!-- <h6 class="text-secondary fw-bold">Announcements</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Properties</th>
                      <th>Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Barangay Assembly Day</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div> -->

              <!-- <h6 class="text-secondary fw-bold">Services Offered</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Icon</th>
                      <th>Service Name</th>
                      <th>Service Description</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td>Barangay ID</td>
                      <td>Opsiyal na identification card na inilalabas ng barangay.</td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div> -->

              <!-- <h6 class="text-secondary fw-bold">News and Updates</h6>
              <div class="table-responsive admin-table">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Cover Photo</th>
                      <th>Date</th>
                      <th>Headline</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td>February 12, 2025</td>
                      <td>Camarines Norte Sets Highest Number of SGLGB Passers</td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div> -->

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

              <h6 class="text-secondary fw-bold">Banner</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Title</th>
                      <th>Background Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>About Us</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h6 class="text-secondary fw-bold">eBarangay Mo</h6>
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
                      <td>Fast. Easy. eBarangay Mo.</td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                    <tr>
                      <td>Subtitle</td>
                      <td>Bringing Barangay Services Closer to You.</td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                    <tr>
                      <td>Description</td>
                      <td>Ang eBarangay Mo ay isang online portal ng Barangay Magang.</td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                    <tr>
                      <td>Image 1</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                    <tr>
                      <td>Image 2</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                    <tr>
                      <td>Image 3</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h6 class="text-secondary fw-bold">Barangay Officials</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Description</th>
                      <th>Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Ipinapakita ang listahan ng mga opisyal na halal ng Barangay Magang.</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h6 class="text-secondary fw-bold">Mission and Vision</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Title</th>
                      <th>Description</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Mission</td>
                      <td>We members of Sangguniang Barangay will continue to strive more to effectively deliver...</td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                    <tr>
                      <td>Vision</td>
                      <td>Barangay Magang is one of the most widely competitive communities in Daet...</td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h6 class="text-secondary fw-bold">Citizen’s Charter</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Description</th>
                      <th>Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Inilalahad ng Citizen’s Charter ang pangako ng Barangay Magang na magbigay ng…</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h6 class="text-secondary fw-bold">Barangay Map</h6>
              <div class="table-responsive admin-table">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Description</th>
                      <th>Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Ang mapang ito ay nagsisilbing visual na gabay para sa mga residente…</td>
                      <td><img src="images/transparency_seal.png" style="height:32px;"></td>
                      <td class="text-end"><button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Services panel (new) -->
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
              
              <!-- Banner -->
              <h6 class="text-secondary fw-bold">Banner</h6>
              <div class="table-responsive admin-table mb-4">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Title</th>
                      <th>Background Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Services</td>
                      <td><img src="images/services_banner.png" alt="Services Banner" style="height:32px;"></td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Barangay Services -->
              <h6 class="text-secondary fw-bold">Barangay Services</h6>
              <div class="table-responsive admin-table">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Icon</th>
                      <th>Service Name</th>
                      <th>Service Description</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><img src="images/icon_barangay_id.png" alt="Barangay ID" style="height:32px;"></td>
                      <td>Barangay ID</td>
                      <td>Opsiyal na identification card na inilalabas ng barangay.</td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                    <tr>
                      <td><img src="images/icon_clearance.png" alt="Clearance" style="height:32px;"></td>
                      <td>Barangay Clearance</td>
                      <td>Description</td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                    <tr>
                      <td><img src="images/icon_certification.png" alt="Certification" style="height:32px;"></td>
                      <td>Certification</td>
                      <td>Description</td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                    <tr>
                      <td><img src="images/icon_permit.png" alt="Permit" style="height:32px;"></td>
                      <td>Business Permit</td>
                      <td>Description</td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                    <tr>
                      <td><img src="images/icon_borrow.png" alt="Equipment Borrowing" style="height:32px;"></td>
                      <td>Equipment Borrowing</td>
                      <td>Description</td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                    <tr>
                      <td><img src="images/icon_cash.png" alt="Cash Incentives" style="height:32px;"></td>
                      <td>Cash Incentives</td>
                      <td>Description</td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Transparency Seal panel (new) -->
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
              
              <!-- Banner -->
              <h6 class="text-secondary fw-bold">Banner</h6>
              <div class="table-responsive admin-table">
                <table class="table table-hover align-middle text-start">
                  <thead class="table-light">
                    <tr>
                      <th>Title</th>
                      <th>Background Image</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Transparency Seal</td>
                      <td><img src="images/transparency_seal_banner.png" alt="Transparency Seal Banner" style="height:32px;"></td>
                      <td class="text-end">
                        <button class="btn btn-success btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

