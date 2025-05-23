<?php
// functions/update_announcements.php
require_once 'dbconn.php';
session_start();

// -- simple auth check
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// 1) Deletion
if (!empty($_POST['delete_id'])) {
  $id = (int)$_POST['delete_id'];
  // fetch old file
  $stmt = $conn->prepare("SELECT image_file FROM announcements WHERE id=?");
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $old = $stmt->get_result()->fetch_assoc()['image_file'] ?? '';
  $stmt->close();
  // delete record
  $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $stmt->close();
  // unlink file
  if ($old && is_file(__DIR__.'/../announcements/'.$old)) {
    @unlink(__DIR__.'/../announcements/'.$old);
  }
  header('Location: ../adminwebsite.php');
  exit;
}

// 2) New upload
if (!empty($_POST['title']) && !empty($_FILES['image']['tmp_name'])) {
  $title = trim($_POST['title']);
  $tmp   = $_FILES['image']['tmp_name'];
  $orig  = basename($_FILES['image']['name']);
  $ext   = strtolower(pathinfo($orig,PATHINFO_EXTENSION));
  $allowed=['png','jpg','jpeg','gif'];
  if (!in_array($ext,$allowed,true)) {
    http_response_code(400);
    exit('Invalid image type');
  }
  // generate unique filename
  $name = uniqid('ann_',true).'.'.$ext;
  $dest = __DIR__.'/../announcements/'.$name;
  if (!move_uploaded_file($tmp,$dest)) {
    http_response_code(500);
    exit('Upload failed');
  }
  // insert
  $stmt = $conn->prepare("
    INSERT INTO announcements(title,image_file)
    VALUES(?,?)
  ");
  $stmt->bind_param('ss',$title,$name);
  $stmt->execute();
  $stmt->close();

  header('Location: ../adminPanel.php?page=adminWebsite');
  exit;
}

http_response_code(400);
exit('Bad request');
