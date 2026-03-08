<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Guest Information — BLD Hotel</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<nav>
  <div class="nav-logo">The Grand <span>BLD</span></div>
  <div class="nav-steps">
    <a href="guestinfo.php" class="nav-step active">
      <span class="num">1</span>
      <span class="label">Guest Info</span>
    </a>
    <div class="nav-divider"></div>
    <span class="nav-step">
      <span class="num">2</span>
      <span class="label">Room</span>
    </span>
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
    <span class="page-tag">Step 1 of 4</span>
    <h1>Tell us about <em>yourself</em></h1>
    <p>Please fill in your personal details. This information will appear on your reservation.</p>
  </div>

  <?php
  $errors = [];
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['first_name','last_name','email','phone','nationality','id_number','check_in','check_out','guests'];
    $data = [];
    foreach ($fields as $f) {
      $data[$f] = trim($_POST[$f] ?? '');
    }
    if (!$data['first_name']) $errors[] = 'First name is required.';
    if (!$data['last_name'])  $errors[] = 'Last name is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (!$data['phone'])      $errors[] = 'Phone number is required.';
    if (!$data['check_in'])   $errors[] = 'Check-in date is required.';
    if (!$data['check_out'])  $errors[] = 'Check-out date is required.';
    if ($data['check_out'] <= $data['check_in']) $errors[] = 'Check-out must be after check-in.';
    if (!$data['guests'])     $errors[] = 'Number of guests is required.';

    if (empty($errors)) {
      $_SESSION['guest'] = $data;
      header('Location: rooms.php');
      exit;
    }
  }
  $d = $_SESSION['guest'] ?? [];
  ?>

  <?php if ($errors): ?>
    <div style="background:#fff3f3;border:1.5px solid #e8b4b4;border-radius:4px;padding:14px 18px;margin-bottom:22px;font-size:13.5px;color:#8b2020;">
      <?php foreach($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" action="guestinfo.php">

      <div class="grid grid-2">
        <div class="field">
          <label>First Name <span class="req">*</span></label>
          <input type="text" name="first_name" placeholder="Maria" value="<?= htmlspecialchars($d['first_name'] ?? '') ?>" required/>
        </div>
        <div class="field">
          <label>Last Name <span class="req">*</span></label>
          <input type="text" name="last_name" placeholder="Santos" value="<?= htmlspecialchars($d['last_name'] ?? '') ?>" required/>
        </div>
        <div class="field">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" name="email" placeholder="maria@email.com" value="<?= htmlspecialchars($d['email'] ?? '') ?>" required/>
        </div>
        <div class="field">
          <label>Phone Number <span class="req">*</span></label>
          <input type="tel" name="phone" placeholder="+63 912 345 6789" value="<?= htmlspecialchars($d['phone'] ?? '') ?>" required/>
        </div>
        <div class="field">
          <label>Nationality</label>
          <select name="nationality">
            <option value="">Select…</option>
            <?php foreach(['Filipino','American','British','Japanese','Korean','Australian','Other'] as $n): ?>
              <option <?= ($d['nationality'] ?? '') === $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>ID / Passport No.</label>
          <input type="text" name="id_number" placeholder="AB1234567" value="<?= htmlspecialchars($d['id_number'] ?? '') ?>"/>
        </div>
      </div>

      <div style="height:22px;border-top:1px solid var(--border);margin:28px 0 22px;position:relative;">
        <span style="position:absolute;top:-10px;left:0;background:#fff;padding-right:12px;font-size:11px;letter-spacing:0.2em;text-transform:uppercase;color:var(--muted);">Stay Dates</span>
      </div>

      <div class="grid grid-2">
        <div class="field">
          <label>Check-in Date <span class="req">*</span></label>
          <input type="date" name="check_in" value="<?= htmlspecialchars($d['check_in'] ?? '') ?>" required/>
        </div>
        <div class="field">
          <label>Check-out Date <span class="req">*</span></label>
          <input type="date" name="check_out" value="<?= htmlspecialchars($d['check_out'] ?? '') ?>" required/>
        </div>
        <div class="field">
          <label>Number of Guests <span class="req">*</span></label>
          <select name="guests" required>
            <option value="">Select…</option>
            <?php foreach(['1 Guest','2 Guests','3 Guests','4 Guests','5+ Guests'] as $g): ?>
              <option <?= ($d['guests'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="btn-row">
        <button type="submit" class="btn-next">Continue to Room &rarr;</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>