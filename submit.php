<?php
require_once "config.php";

function redirect_with($params) {
  header("Location: index.php?" . http_build_query($params));
  exit;
}

$member_name   = trim($_POST["member_name"] ?? "");
$invited_count = $_POST["invited_count"] ?? "";
$guest_names   = trim($_POST["guest_names"] ?? "");

if ($member_name === "" || $guest_names === "" || $invited_count === "") {
  redirect_with(["error" => "Please fill in all fields."]);
}
if (!is_numeric($invited_count)) {
  redirect_with(["error" => "Invited count must be a number."]);
}

$invited_count = (int)$invited_count;
if ($invited_count < 0 || $invited_count > 50) {
  redirect_with(["error" => "Invited count must be between 0 and 50."]);
}

// Clean guest names: normalize newlines, remove empty lines, trim each line
$guest_names = str_replace(["\r\n", "\r"], "\n", $guest_names);
$lines = explode("\n", $guest_names);

$clean = [];
foreach ($lines as $line) {
  $name = trim($line);
  if ($name !== "") {
    if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);
    $clean[] = $name;
  }
}

if (count($clean) === 0) {
  redirect_with(["error" => "Please enter at least one guest name."]);
}

$guest_names_clean = implode("\n", $clean);

$note = "";
if ($invited_count !== count($clean)) {
  $note = " Saved, but invited count ($invited_count) does not match names entered (" . count($clean) . ").";
}

// Use md5+uniqid for compatibility on older shared hosting (avoids random_bytes() fatal)
$delete_token = md5(uniqid((string)mt_rand(), true));

try {
  $conn->begin_transaction();

  // Insert invite
  $stmt = $conn->prepare(
    "INSERT INTO invites (member_name, invited_count, guest_names, delete_token)
     VALUES (?, ?, ?, ?)"
  );
  $stmt->bind_param("siss", $member_name, $invited_count, $guest_names_clean, $delete_token);
  $stmt->execute();

  $invite_id = $conn->insert_id;

  // Insert per-guest rows for RSVP tracking
  $gstmt = $conn->prepare(
    "INSERT INTO guests (invite_id, guest_name, rsvp_status, rsvp_token)
     VALUES (?, ?, 'PENDING', ?)"
  );

  foreach ($clean as $guest) {
    $rsvp_token = md5(uniqid((string)mt_rand(), true));
    $gstmt->bind_param("iss", $invite_id, $guest, $rsvp_token);
    $gstmt->execute();
  }

  $conn->commit();
  redirect_with(["success" => "Invitation saved successfully.$note"]);

} catch (Exception $ex) {
  $conn->rollback();
  redirect_with(["error" => "Failed to save invitation. " . $ex->getMessage()]);
}
