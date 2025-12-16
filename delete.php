<?php
require_once "config.php";

function redirect_home($msg, $is_error = false) {
  $key = $is_error ? "error" : "success";
  header("Location: index.php?" . http_build_query([$key => $msg]));
  exit;
}

$id    = $_POST["id"] ?? "";
$token = $_POST["token"] ?? "";

if (!is_numeric($id) || $token === "") {
  redirect_home("Invalid delete request.", true);
}

$id = (int)$id;

try {
  $conn->begin_transaction();

  // If FK cascade isn't enabled, remove guests manually
  $g = $conn->prepare("DELETE FROM guests WHERE invite_id = ?");
  $g->bind_param("i", $id);
  $g->execute();

  $stmt = $conn->prepare("DELETE FROM invites WHERE id = ? AND delete_token = ?");
  $stmt->bind_param("is", $id, $token);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    $conn->commit();
    redirect_home("Deleted successfully.");
  } else {
    $conn->rollback();
    redirect_home("Delete failed (already deleted or invalid token).", true);
  }

} catch (Exception $ex) {
  $conn->rollback();
  redirect_home("Delete error: " . $ex->getMessage(), true);
}
