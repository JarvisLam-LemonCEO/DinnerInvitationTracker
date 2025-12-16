<?php
require_once "config.php";

function e($str) {
  return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8");
}

$success = $_GET["success"] ?? "";
$error   = $_GET["error"] ?? "";


// Fetch invites
$invites = [];
$res = $conn->query(
  "SELECT id, member_name, invited_count, delete_token, created_at
   FROM invites
   ORDER BY created_at DESC, id DESC"
);
while ($row = $res->fetch_assoc()) {
  $invites[] = $row;
}

// Fetch guests grouped by invite_id
$guestsByInvite = [];
$gres = $conn->query(
  "SELECT id, invite_id, guest_name, rsvp_status, rsvp_token
   FROM guests
   ORDER BY invite_id DESC, id ASC"
);
while ($g = $gres->fetch_assoc()) {
  $guestsByInvite[$g["invite_id"]][] = $g;
}
  
  $summary = [
    "CONFIRMED"     => [],
    "NOT_CONFIRMED" => [],
    "PENDING"       => []
  ];
  
  foreach ($guestsByInvite as $inviteId => $guests) {
    foreach ($guests as $g) {
      $status = $g["rsvp_status"] ?: "PENDING";
      if (!isset($summary[$status])) $status = "PENDING";
      $summary[$status][] = $g["guest_name"];
    }
  }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dinner Invitation Tracker</title>
  <style>
    :root{
      --bg:#0b1220; --card:#0f1b33; --muted:#9fb0d0; --text:#eaf0ff;
      --accent:#6ea8fe; --good:#7ee787; --danger:#ff6b6b; --border:rgba(255,255,255,.10);
      --shadow: 0 10px 30px rgba(0,0,0,.35); --radius: 16px;
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
      color:var(--text);
      background: radial-gradient(1200px 600px at 20% 10%, rgba(110,168,254,.25), transparent 60%),
                  radial-gradient(900px 500px at 80% 0%, rgba(126,231,135,.18), transparent 55%),
                  linear-gradient(180deg, var(--bg), #070b14 70%);
      min-height:100vh;
    }
    .container{ width:min(1150px, 92vw); margin: 32px auto 60px; }
    header{ display:flex; gap:16px; align-items:flex-end; justify-content:space-between; margin-bottom:18px; }
    .title h1{ margin:0; font-size: clamp(22px, 3vw, 34px); letter-spacing:.2px; }
    .title p{ margin:8px 0 0; color:var(--muted); font-size:14px; }
    .badge{ padding:6px 10px; border-radius:999px; font-size:12px; background: rgba(110,168,254,.14); border:1px solid rgba(110,168,254,.35); }
    .grid{ display:grid; grid-template-columns: 1fr; gap:18px; }
    @media (min-width: 960px){ .grid{ grid-template-columns: 420px 1fr; align-items:start; } }
    .card{ background: rgba(15,27,51,.85); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow:hidden; }
    .card .head{ padding:16px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .pill{ display:inline-block; padding:4px 10px; border-radius:999px; background: rgba(110,168,254,.14); border:1px solid rgba(110,168,254,.35); font-size:12px; }
    .body{ padding: 18px; }
    label{ display:block; margin: 12px 0 6px; font-size: 13px; color: var(--muted); }
    input, textarea{
      width:100%; padding: 10px 12px; border-radius: 12px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(10,16,30,.55);
      color: var(--text);
      outline: none;
    }
    input:focus, textarea:focus{ border-color: rgba(110,168,254,.55); box-shadow: 0 0 0 3px rgba(110,168,254,.18); }
    textarea{ min-height: 130px; resize: vertical; }
    .hint{ margin-top:6px; font-size:12px; color: rgba(159,176,208,.9); }
    .btn{
      width:100%; margin-top:14px; padding: 12px 14px;
      border: none; border-radius: 12px;
      background: linear-gradient(135deg, rgba(110,168,254,1), rgba(126,231,135,1));
      color:#06101f; font-weight: 700; cursor:pointer;
    }
    .btn:hover{ filter: brightness(1.03); }
    .alert{ margin: 0 0 12px; padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border); background: rgba(255,255,255,.06); font-size: 13px; }
    .alert.success{ border-color: rgba(126,231,135,.45); }
    .alert.error{ border-color: rgba(255,107,107,.55); }
    table{ width:100%; border-collapse: collapse; font-size: 14px; }
    th, td{ padding: 12px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
    th{ text-align:left; color: var(--muted); font-weight: 600; font-size: 12px; letter-spacing:.3px; text-transform: uppercase; }

    .status{ font-weight:700; font-size:12px; padding:4px 10px; border-radius:999px; display:inline-block; border:1px solid var(--border); }
    .PENDING{ background:rgba(255,255,255,.06); }
    .CONFIRMED{ background:rgba(126,231,135,.14); border-color:rgba(126,231,135,.35); }
    .NOT_CONFIRMED{ background:rgba(255,107,107,.14); border-color:rgba(255,107,107,.35); }

    .miniBtn{ padding:7px 10px; border-radius:10px; border:1px solid rgba(255,255,255,.14); background:rgba(110,168,254,.14); color:var(--text); cursor:pointer; font-size:12px; }
    .miniBtn.good{ background:rgba(126,231,135,.18); }
    .miniBtn.danger{ background:rgba(255,107,107,.18); }

    .guestRow{ display:flex; gap:10px; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px dashed rgba(255,255,255,.10); }
    .guestRow:last-child{ border-bottom:none; }
    .guestLeft{ display:flex; gap:10px; align-items:center; }
    .guestName{ font-size:13px; }
    .actions{ display:flex; gap:8px; align-items:center; }
    
    /* ===== RSVP SUMMARY CARDS ===== */
    
    .summary-cards{
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 18px;
      padding: 18px;
    }
    
    .summary-card{
      background: linear-gradient(
        180deg,
        rgba(15,27,51,.95),
        rgba(10,18,36,.92)
      );
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 18px;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
    }
    
    .summary-card::after{
      content:"";
      position:absolute;
      inset:0;
      background: radial-gradient(
        600px 200px at -10% -20%,
        rgba(255,255,255,.06),
        transparent 60%
      );
      pointer-events:none;
    }
    
    .summary-card h3{
      margin: 0;
      font-size: 15px;
      letter-spacing: .3px;
      text-transform: uppercase;
      color: var(--muted);
    }
    
    .summary-count{
      font-size: 34px;
      font-weight: 800;
      margin: 8px 0 12px;
      letter-spacing: .5px;
    }
    
    .summary-card ul{
      margin: 0;
      padding-left: 18px;
      font-size: 14px;
    }
    
    .summary-card li{
      margin-bottom: 6px;
      line-height: 1.3;
    }
    
    .summary-card em{
      color: rgba(159,176,208,.8);
    }
    
    /* Status accents */
    .summary-card.confirmed{
      border-left: 5px solid var(--good);
    }
    .summary-card.confirmed .summary-count{
      color: var(--good);
    }
    
    .summary-card.not-confirmed{
      border-left: 5px solid var(--danger);
    }
    .summary-card.not-confirmed .summary-count{
      color: var(--danger);
    }
    
    .summary-card.pending{
      border-left: 5px solid var(--accent);
    }
    .summary-card.pending .summary-count{
      color: var(--accent);
    }


    footer{ margin-top: 16px; color: rgba(159,176,208,.8); font-size: 12px; text-align:center; }
    
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="title">
        <h1>Thursday Dinner Invitation Tracker</h1>
        <p>Members add invited guests. Anyone can mark Confirm / Not Confirm and delete entries.</p>
      </div>
      <div class="badge">No login required</div>
    </header>

    <div class="grid">
      <section class="card">
        <div class="head"><strong>Add Your Invite</strong><span class="pill">Public page</span></div>
        <div class="body">
          <?php if ($success): ?><div class="alert success"><?php echo e($success); ?></div><?php endif; ?>
          <?php if ($error): ?><div class="alert error"><?php echo e($error); ?></div><?php endif; ?>

          <form method="post" action="submit.php">
            <label for="member_name">Your Name</label>
            <input id="member_name" name="member_name" maxlength="100" required placeholder="e.g., Jarvis" />

            <label for="invited_count">Number of People You Invited</label>
            <input id="invited_count" name="invited_count" type="number" min="0" max="50" required placeholder="e.g., 3" />

            <label for="guest_names">Invited People Names (one per line)</label>
            <textarea id="guest_names" name="guest_names" required placeholder="Amy Chan&#10;Ben Lee&#10;Chris Wong"></textarea>
            <div class="hint">Tip: Use one name per line. RSVP starts as PENDING.</div>

            <button class="btn" type="submit">Save Invitation</button>
          </form>
        </div>
      </section>

  
  
      <section class="card">


        <div class="head"><strong>Invitation List</strong><span class="pill"><?php echo count($invites); ?> record(s)</span></div>
        <div class="body" style="padding:0">
          <div style="overflow:auto">
            <table>
              <thead>
                <tr>
                  <th style="width: 170px;">Time</th>
                  <th style="width: 160px;">Member</th>
                  <th style="width: 90px;">Invited #</th>
                  <th>Guests + RSVP</th>
                  <th style="width: 110px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($invites) > 0): ?>
                  <?php foreach ($invites as $inv): ?>
                    <tr>
                      <td><?php echo e($inv["created_at"]); ?></td>
                      <td><strong><?php echo e($inv["member_name"]); ?></strong></td>
                      <td><span class="pill"><?php echo (int)$inv["invited_count"]; ?></span></td>
                      <td>
                        <?php $inviteId = (int)$inv["id"]; $guestList = $guestsByInvite[$inviteId] ?? []; ?>
                        <?php if (count($guestList) === 0): ?>
                          <span style="color:rgba(159,176,208,.9);">No guests found (old record). Re-submit, or run a migration script.</span>
                        <?php else: ?>
                          <?php foreach ($guestList as $g): ?>
                            <div class="guestRow">
                              <div class="guestLeft">
                                <span class="guestName"><?php echo e($g["guest_name"]); ?></span>
                                <span class="status <?php echo e($g["rsvp_status"]); ?>"><?php echo e($g["rsvp_status"]); ?></span>
                              </div>
                              <div class="actions">
                                <form method="post" action="rsvp.php" style="margin:0;">
                                  <input type="hidden" name="guest_id" value="<?php echo (int)$g["id"]; ?>">
                                  <input type="hidden" name="token" value="<?php echo e($g["rsvp_token"]); ?>">
                                  <input type="hidden" name="action" value="confirm">
                                  <button class="miniBtn good" type="submit">Confirm</button>
                                </form>

                                <form method="post" action="rsvp.php" style="margin:0;">
                                  <input type="hidden" name="guest_id" value="<?php echo (int)$g["id"]; ?>">
                                  <input type="hidden" name="token" value="<?php echo e($g["rsvp_token"]); ?>">
                                  <input type="hidden" name="action" value="not_confirm">
                                  <button class="miniBtn danger" type="submit">Not Confirm</button>
                                </form>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <form method="post" action="delete.php" onsubmit="return confirm('Delete this invite and all guests?');" style="margin:0;">
                          <input type="hidden" name="id" value="<?php echo (int)$inv["id"]; ?>">
                          <input type="hidden" name="token" value="<?php echo e($inv["delete_token"]); ?>">
                          <button class="miniBtn danger" type="submit">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" style="padding:18px;color:rgba(159,176,208,.9);">No invitations yet. Be the first to add one.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
  
  
      <section class="card">
        <div class="head">
          <strong>RSVP Summary</strong>
          <span class="pill"><?= count($summary["CONFIRMED"]) + count($summary["NOT_CONFIRMED"]) + count($summary["PENDING"]) ?> guests</span>
        </div>

      
      <div class="summary-cards">
        
        <!-- Confirmed -->
        <div class="summary-card confirmed">
          <h3>Confirmed</h3>
          <div class="summary-count"><?= count($summary["CONFIRMED"]) ?></div>
          <ul>
            <?php if ($summary["CONFIRMED"]): ?>
            <?php foreach ($summary["CONFIRMED"] as $name): ?>
            <li><?= e($name) ?></li>
            <?php endforeach; ?>
            <?php else: ?>
            <li><em>No confirmed guests</em></li>
            <?php endif; ?>
          </ul>
        </div>
        
        <!-- Not Confirmed -->
        <div class="summary-card not-confirmed">
          <h3>Not Confirmed</h3>
          <div class="summary-count"><?= count($summary["NOT_CONFIRMED"]) ?></div>
          <ul>
            <?php if ($summary["NOT_CONFIRMED"]): ?>
            <?php foreach ($summary["NOT_CONFIRMED"] as $name): ?>
            <li><?= e($name) ?></li>
            <?php endforeach; ?>
            <?php else: ?>
            <li><em>No declined guests</em></li>
            <?php endif; ?>
          </ul>
        </div>
        
        <!-- Pending -->
        <div class="summary-card pending">
          <h3>Pending</h3>
          <div class="summary-count"><?= count($summary["PENDING"]) ?></div>
          <ul>
            <?php if ($summary["PENDING"]): ?>
            <?php foreach ($summary["PENDING"] as $name): ?>
            <li><?= e($name) ?></li>
            <?php endforeach; ?>
            <?php else: ?>
            <li><em>No pending responses</em></li>
            <?php endif; ?>
          </ul>
        </div>
        
      </div>
      </section>
  
    <footer>
      Designed By Jarvis Lam.
    </footer>
  </div>
</body>
</html>
    
