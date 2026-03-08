<?php
session_start();
if (empty($_SESSION['guest'])) { header('Location: guestinfo.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $room = $_POST['room_type'] ?? '';
  $bed  = $_POST['bed_pref'] ?? '';
  $floor = $_POST['floor_pref'] ?? '';
  $notes = trim($_POST['special_notes'] ?? '');
  if ($room) {
    $_SESSION['room'] = compact('room','bed','floor','notes');
    header('Location: foods.php');
    exit;
  }
  $error = 'Please select a room type.';
}
$r = $_SESSION['room'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Room Selection — Grand Maison</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<nav>
  <div class="nav-logo">The Grand <span>Maison</span></div>
  <div class="nav-steps">
    <a href="guest.php" class="nav-step done">
      <span class="num">✓</span>
      <span class="label">Guest Info</span>
    </a>
    <div class="nav-divider"></div>
    <a href="rooms.php" class="nav-step active">
      <span class="num">2</span>
      <span class="label">Room</span>
    </a>
    <div class="nav-divider"></div>
    <span class="nav-step">
      <span class="num">3</span>
      <span class="label">Food</span>
    </span>
    <div class="nav-divider"></div>
    <span class="nav-step">
      <span class="num">4</span>
      <span class="label">Confirm</span>
    </span>
  </div>
</nav>

<div class="page">
  <div class="page-head">
    <span class="page-tag">Step 2 of 4</span>
    <h1>Choose your <em>room</em></h1>
    <p>All rooms include complimentary breakfast, Wi-Fi, and access to our rooftop pool.</p>
  </div>

  <?php if (!empty($error)): ?>
    <div style="background:#fff3f3;border:1.5px solid #e8b4b4;border-radius:4px;padding:14px 18px;margin-bottom:22px;font-size:13.5px;color:#8b2020;">⚠ <?= $error ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" action="rooms.php">

      <div class="room-grid">
        <?php
        $rooms = [
          ['Deluxe Room',         '🛏',  '₱4,500/night', '35 m² · King or Twin · City view · Sleeps 2'],
          ['Junior Suite',        '🌅',  '₱7,200/night', '55 m² · King bed · Garden view · Sitting lounge'],
          ['Premier Suite',       '🌃',  '₱11,500/night','75 m² · King bed · Skyline view · Walk-in closet'],
          ['Presidential Suite',  '👑',  '₱18,000/night','120 m² · Master bedroom · Panoramic · Private butler'],
        ];
        foreach ($rooms as [$name, $icon, $price, $desc]):
          $checked = ($r['room'] ?? '') === $name ? 'checked' : '';
        ?>
        <div class="room-option">
          <input type="radio" name="room_type" id="<?= md5($name) ?>" value="<?= $name ?>" <?= $checked ?> required/>
          <label for="<?= md5($name) ?>">
            <span class="room-icon"><?= $icon ?></span>
            <div class="room-info">
              <div class="room-name"><?= $name ?></div>
              <div class="room-desc"><?= $desc ?></div>
            </div>
            <span class="room-price"><?= $price ?></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="height:22px;border-top:1px solid var(--border);margin:28px 0 22px;position:relative;">
        <span style="position:absolute;top:-10px;left:0;background:#fff;padding-right:12px;font-size:11px;letter-spacing:0.2em;text-transform:uppercase;color:var(--muted);">Preferences</span>
      </div>

      <div class="grid grid-2">
        <div class="field">
          <label>Bed Preference</label>
          <select name="bed_pref">
            <option value="">No preference</option>
            <?php foreach(['King Bed','Queen Bed','Twin Beds'] as $b): ?>
              <option <?= ($r['bed'] ?? '') === $b ? 'selected':'' ?>><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Floor Preference</label>
          <select name="floor_pref">
            <option value="">No preference</option>
            <?php foreach(['Low (1–5)','Mid (6–12)','High (13+)'] as $f): ?>
              <option <?= ($r['floor'] ?? '') === $f ? 'selected':'' ?>><?= $f ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field" style="margin-top:18px">
        <label>Special Notes</label>
        <textarea name="special_notes" placeholder="Allergies, anniversary setup, accessibility needs…"><?= htmlspecialchars($r['notes'] ?? '') ?></textarea>
      </div>

      <div class="btn-row">
        <a href="guest.php" class="btn-back">&larr; Back</a>
        <button type="submit" class="btn-next">Continue to Food &rarr;</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>