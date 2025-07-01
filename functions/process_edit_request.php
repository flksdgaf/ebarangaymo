<?php
session_start();
require 'dbconn.php';
if (!isset($_SESSION['auth'])) exit('No auth');

// map type → table + editable columns
$tableMap = [
  'Barangay ID' => [
    'table' => 'barangay_id_requests',
    'cols' => [
      'transaction_type','full_name','purok','birth_date','birth_place',
      'civil_status','religion','height','weight',
      'emergency_contact_person','emergency_contact_number',
      'formal_picture','claim_date','payment_method'
    ]
  ],

  'Business Permit' => [
    'table' => 'business_permit_requests',
    'cols' => [
      'transaction_type','full_name','purok','barangay','age','civil_status','name_of_business','type_of_business',
      'full_address','claim_date','payment_method'
        ]
    ],
    
    'Good Moral' => [
        'table' => 'good_moral_requests',
        'cols' => [
            'full_name','civil_status','sex','age','purok','subdivision','purpose',
            'claim_date','payment_method'
        ]
    ],

    'Guardianship' => [
        'table' => 'guardianship_requests',
        'cols' => [
            'full_name','civil_status','age','purok','child_name','purpose',
            'claim_date','payment_method'
        ]
    ],

    'Indigency' => [
        'table' => 'indigency_requests',
        'cols' => [
            'full_name','civil_status','age','purok','purpose',
            'claim_date'
        ]
    ],

    'Residency' => [
        'table' => 'residency_requests',
        'cols' => [
            'full_name','civil_status','age','purok','residing_years',
            'purpose','claim_date','payment_method'
        ]
    ],

    'Solo Parent' => [
        'table' => 'solo_parent_requests',
        'cols' => [
            'full_name','civil_status','age','purok','years_solo_parent',
            'child_name','child_sex','child_age','purpose','claim_date','payment_method'
        ]
    ]

];

$type = $_POST['request_type'] ?? '';
$tid  = $_POST['transaction_id'] ?? '';
if (!isset($tableMap[$type])) {
  die("Unknown type");
}

$meta = $tableMap[$type];
$table = $meta['table'];
$cols  = $meta['cols'];

// 1) fetch existing
$stmt = $conn->prepare("SELECT " . implode(',', $cols) . " FROM `{$table}` WHERE transaction_id = ? LIMIT 1");
$stmt->bind_param('s', $tid);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// 2) collect incoming & detect changes
$updates = [];
$params  = [];
$types   = '';
foreach ($cols as $col) {
  if ($col === 'formal_picture') {
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
      $fn = uniqid().'_'.basename($_FILES['photo']['name']);
      move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__.'/../barangayIDpictures/'.$fn);
      $incoming = $fn;
    } else {
      continue;  // no change if no new file
    }
  } else {
    // map date→dob, etc
    $name = $col==='birth_date' ? 'dob' : $col;
    $incoming = $_POST[$name] ?? '';
  }

  // compare strings (NULL vs '')
  $orig = $existing[$col] ?? null;
  if ((string)$incoming !== (string)$orig) {
    $updates[] = "`{$col}` = ?";
    $params[]  = $incoming;
    // pick bind‐type
    $types .= is_numeric($incoming) && $col!=='birth_date' ? 'd' : 's';
  }
}

// 3) if nothing changed
if (empty($updates)) {
  header("Location: ../adminPanel.php?page=adminRequest&nochange=1");
  exit;
}

// 4) build & run UPDATE
$updates[] = "`updated_at` = NOW()";
$sql = "UPDATE `{$table}` SET " . implode(',', $updates) . " WHERE transaction_id = ?";
$params[] = $tid;
$types   .= 's';

$up = $conn->prepare($sql);
$up->bind_param($types, ...$params);
$up->execute();
$up->close();

// 5) log activity
$log = $conn->prepare("
  INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description)
  VALUES (?,?,?,?,?,?)
");
$action = 'UPDATE';
$desc   = "Updated {$type} Request";
$log->bind_param(
  'isssss',
  $_SESSION['loggedInUserID'],
  $_SESSION['loggedInUserRole'],
  $action,
  $table,
  $tid,
  $desc
);
$log->execute();
$log->close();

// 6) redirect back with success
header("Location: ../adminPanel.php?page=adminRequest&updated_request_id=$tid");
exit;
?>
