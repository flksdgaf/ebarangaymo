<?php
include 'functions/dbconn.php';

// 1) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function saveSetting($conn, $key, $value) {
        $stmt = $conn->prepare("
            REPLACE INTO settings (setting_key, setting_value)
            VALUES (?, ?)
        ");
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }

    // Text settings
    saveSetting($conn, 'barangay_name', $_POST['barangay_name']);
    saveSetting($conn, 'barangay_tag',  $_POST['barangay_tag']);
    saveSetting($conn, 'primary_color', $_POST['primary_color']);

    // File uploads
    foreach (['logo1','logo2'] as $logoKey) {
        if (
          isset($_FILES[$logoKey])
          && is_uploaded_file($_FILES[$logoKey]['tmp_name'])
        ) {
            $ext  = pathinfo($_FILES[$logoKey]['name'], PATHINFO_EXTENSION);
            $dest = "uploads/{$logoKey}." . $ext;
            move_uploaded_file($_FILES[$logoKey]['tmp_name'], $dest);
            saveSetting($conn, $logoKey, $dest);
        }
    }

    echo "<div class='alert alert-success'>Settings updated!</div>";
}

// 2) Fetch current settings into one array
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM superadmin_settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$res->free();

// 3) Pull out each into a scalar (so you never get an array)
$barangay_name  = $settings['barangay_name']  ?? 'Barangay Magang';
$barangay_tag   = $settings['barangay_tag']   ?? 'Daet, Camarines Norte';
$primary_color  = $settings['primary_color']  ?? '#28a745';
$logo1          = $settings['logo1']          ?? 'images/good_governance_logo.png';
$logo2          = $settings['logo2']          ?? 'images/magang_logo.png';
?>

<div class="container py-3">
  <h2>Admin Settings</h2>
  <form method="POST" enctype="multipart/form-data" class="row g-3">

    <div class="col-md-6">
      <label for="barangay_name" class="form-label">Barangay Name</label>
      <input type="text" id="barangay_name" name="barangay_name"
             class="form-control"
             value="<?= htmlspecialchars($barangay_name, ENT_QUOTES) ?>" required>
    </div>

    <div class="col-md-6">
      <label for="barangay_tag" class="form-label">Location Tagline</label>
      <input type="text" id="barangay_tag" name="barangay_tag"
             class="form-control"
             value="<?= htmlspecialchars($barangay_tag, ENT_QUOTES) ?>" required>
    </div>

    <div class="col-md-4">
      <label for="primary_color" class="form-label">Primary Color</label>
      <input type="color" id="primary_color" name="primary_color"
             class="form-control form-control-color"
             value="<?= htmlspecialchars($primary_color, ENT_QUOTES) ?>"
             title="Choose primary sidebar color">
    </div>

    <div class="col-md-4">
      <label for="logo1" class="form-label">Logo 1 (Good Governance)</label>
      <input class="form-control" type="file" id="logo1" name="logo1" accept="image/*">
      <small>Current:</small><br>
      <img src="<?= htmlspecialchars($logo1, ENT_QUOTES) ?>" style="height:40px;">
    </div>

    <div class="col-md-4">
      <label for="logo2" class="form-label">Logo 2 (Barangay)</label>
      <input class="form-control" type="file" id="logo2" name="logo2" accept="image/*">
      <small>Current:</small><br>
      <img src="<?= htmlspecialchars($logo2, ENT_QUOTES) ?>" style="height:40px;">
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
  </form>
</div>

<script>
// Live‐preview the new primary color in all sidebars
document.getElementById('primary_color').addEventListener('input', function(){
  document.querySelectorAll('.sidebar').forEach(sb => {
    sb.style.backgroundColor = this.value;
  });
});
</script>
