<?php
// functions/news_update.php
require 'dbconn.php';
session_start();
// 1) auth
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// 2) deletion?
if(!empty($_POST['action']) && $_POST['action']==='delete' && !empty($_POST['id'])){
  $stmt = $conn->prepare("DELETE FROM news_updates WHERE id=?");
  $stmt->bind_param('i',$_POST['id']);
  $stmt->execute();
  exit;
}

// 3) add/edit
$id   = !empty($_POST['id']) ? (int)$_POST['id'] : null;
$date = $_POST['date'];
$head = $_POST['headline'];
$link = $_POST['link'];

$filename = null;
if(!empty($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])){
  $tmp  = $_FILES['cover']['tmp_name'];
  $name = basename($_FILES['cover']['name']);
  $dest = __DIR__.'/../news/'.$name;
  move_uploaded_file($tmp,$dest);
  $filename = $name;
}

// 4) upsert
if($id){
  // update
  if($filename){
    $stmt = $conn->prepare(
      "UPDATE news_updates 
          SET date=?, headline=?, link=?, cover_file=? 
        WHERE id=?"
    );
    $stmt->bind_param('ssssi',$date,$head,$link,$filename,$id);
  } else {
    $stmt = $conn->prepare(
      "UPDATE news_updates 
          SET date=?, headline=?, link=? 
        WHERE id=?"
    );
    $stmt->bind_param('sssi',$date,$head,$link,$id);
  }
} else {
  // insert
  $stmt = $conn->prepare(
    "INSERT INTO news_updates 
      (date,headline,link,cover_file) 
     VALUES(?,?,?,?)"
  );
  $stmt->bind_param('ssss',$date,$head,$link,$filename);
}

$stmt->execute();
$stmt->close();

// redirect back
header('Location: ../adminPanel.php?page=adminWebsite');
exit;
