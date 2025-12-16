<?php
require_once "config.php";

function redirect_home($msg, $is_error = false) {
  $key = $is_error ? "error" : "success";
  header("Location: index.php?" . http_build_query([$key => $msg]));
  exit;
}

$guest_id = $_POST["guest_id"] ?? "";
$token    = $_POST["token"] ?? "";
$action   = $_POST["action"] ?? "";

if (!is_numeric($guest_id) || $token === "" || ($action !== "confirm" && $action !== "not_confirm")) {
  redirect_home("Invalid RSVP request.", true);
}

$guest_id = (int)$guest_id;
$new_status = ($action === "confirm") ? "CONFIRMED" : "NOT_CONFIRMED";

$stmt = $conn->prepare(
  "UPDATE guests
   SET rsvp_status = ?, updated_at = NOW()
   WHERE id = ? AND rsvp_token = ?"
);
$stmt->bind_param("sis", $new_status, $guest_id, $token);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  redirect_home("RSVP updated.");
} else {
  redirect_home("RSVP update failed (invalid token or already updated).", true);
}
