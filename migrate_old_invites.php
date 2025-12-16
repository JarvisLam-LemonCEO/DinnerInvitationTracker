<?php
// migrate_old_invites.php
// One-time migration: takes invites.guest_names (newline-separated) and creates guests rows if missing.
// After running once, delete this file from the server.

require_once "config.php";

function e($s){ return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }

// Find invites that have no guests rows yet
$invRes = $conn->query(
  "SELECT i.id, i.guest_names
   FROM invites i
   LEFT JOIN guests g ON g.invite_id = i.id
   WHERE g.id IS NULL"
);

$migratedInvites = 0;
$migratedGuests = 0;

try {
  $conn->begin_transaction();

  $gstmt = $conn->prepare(
    "INSERT INTO guests (invite_id, guest_name, rsvp_status, rsvp_token)
     VALUES (?, ?, 'PENDING', ?)"
  );

  while ($inv = $invRes->fetch_assoc()) {
    $invite_id = (int)$inv['id'];
    $guest_names = trim((string)$inv['guest_names']);

    if ($guest_names === "") continue;

    $guest_names = str_replace(["\r\n", "\r"], "\n", $guest_names);
    $lines = explode("\n", $guest_names);

    $addedThisInvite = 0;
    foreach ($lines as $line) {
      $name = trim($line);
      if ($name === "") continue;
      if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);

      $token = md5(uniqid((string)mt_rand(), true));
      $gstmt->bind_param("iss", $invite_id, $name, $token);
      $gstmt->execute();
      $migratedGuests++;
      $addedThisInvite++;
    }

    if ($addedThisInvite > 0) $migratedInvites++;
  }

  $conn->commit();

} catch (Exception $ex) {
  $conn->rollback();
  echo "Migration failed: " . e($ex->getMessage());
  exit;
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Migration Result</title></head>
<body style="font-family:system-ui; padding:20px;">
  <h2>Migration complete</h2>
  <p>Invites migrated: <strong><?php echo (int)$migratedInvites; ?></strong></p>
  <p>Guests created: <strong><?php echo (int)$migratedGuests; ?></strong></p>
  <p><strong>Important:</strong> delete <code>migrate_old_invites.php</code> from your server now.</p>
</body></html>
