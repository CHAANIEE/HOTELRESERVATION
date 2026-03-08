<?php
session_start();
require_once __DIR__ . '/../db.php';

if (empty($_SESSION['guest'])) { header('Location: guestinfo.php'); exit; }
if (empty($_SESSION['room']))  { header('Location: rooms.php');     exit; }

$g     = $_SESSION['guest'];
$r     = $_SESSION['room'];
$foods = $_SESSION['food'] ?? [];

$roomPrices = [
    'Deluxe Room'        => 4500,  'Junior Suite'       => 7200,
    'Premier Suite'      => 11500, 'Presidential Suite' => 18000,
];
$foodPriceMap = [
    'Filipino Breakfast Set'  => 350,  'Continental Breakfast'  => 280,
    'American Breakfast'      => 420,  'Healthy Granola Bowl'   => 220,
    'Kare-Kare Platter'       => 580,  'Grilled Sea Bass'       => 650,
    'Caesar Salad & Sandwich' => 390,  'Pasta Aglio e Olio'     => 440,
    'Wagyu Beef Steak'        => 1850, 'Seafood Platter'        => 1200,
    'Chicken Inasal'          => 480,  'Vegetarian Tasting Menu'=> 690,
    'Leche Flan'              => 180,  'Mango Float'            => 210,
    'Sparkling Wine Bottle'   => 1400, 'Fresh Fruit Basket'     => 350,
];

$ci        = new DateTime($g['check_in']);
$co        = new DateTime($g['check_out']);
$nights    = max(1, $ci->diff($co)->days);
$roomRate  = $roomPrices[$r['room']] ?? 0;
$roomTotal = $roomRate * $nights;
$foodTotal = array_sum(array_map(fn($f) => $foodPriceMap[$f] ?? 0, $foods));
$grandTotal = $roomTotal + $foodTotal;

$confirmed = false;
$refNo     = '';
$dbError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();
        $numGuests = (int) filter_var($g['guests'], FILTER_SANITIZE_NUMBER_INT);

        $stmt = $pdo->prepare("CALL sp_create_reservation(
            :first_name, :last_name, :email, :phone,
            :nationality, :id_number,
            :room_type, :check_in, :check_out,
            :num_guests, :bed_pref, :floor_pref, :notes,
            :food_names, @ref_out
        )");
        $stmt->execute([
            ':first_name'  => $g['first_name'],
            ':last_name'   => $g['last_name'],
            ':email'       => $g['email'],
            ':phone'       => $g['phone'],
            ':nationality' => $g['nationality'] ?? '',
            ':id_number'   => $g['id_number']   ?? '',
            ':room_type'   => $r['room'],
            ':check_in'    => $g['check_in'],
            ':check_out'   => $g['check_out'],
            ':num_guests'  => $numGuests,
            ':bed_pref'    => $r['bed']   ?? '',
            ':floor_pref'  => $r['floor'] ?? '',
            ':notes'       => $r['notes'] ?? '',
            ':food_names'  => implode(',', $foods),
        ]);

        $row   = $pdo->query("SELECT @ref_out AS ref")->fetch();
        $refNo = $row['ref'] ?? 'N/A';

        session_unset(); session_destroy();
        $confirmed = true;

    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Detect our custom DOUBLE_BOOKING signal
        if (str_contains($msg, 'DOUBLE_BOOKING')) {
            // Extract the clean part after the prefix
            $clean = preg_replace('/.*DOUBLE_BOOKING:\s*/', '', $msg);
            $dbError = '🚫 This room is already booked for your selected dates. ' . $clean
                     . ' Please go back and choose different dates or another room type.';
        } else {
            $dbError = 'Could not save reservation: ' . $msg;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Confirm Reservation — Grand BLD Hotel</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    .confirm-banner { background:linear-gradient(135deg,#1a5c38,#0e3d24);border-radius:4px;padding:44px 40px;text-align:center;color:#fff;margin-bottom:30px;animation:fadeUp .5s ease; }
    .confirm-banner .tick { width:56px;height:56px;border-radius:50%;border:2px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 16px; }
    .confirm-banner h2 { font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:400;margin-bottom:8px; }
    .confirm-banner p { font-size:13px;opacity:.75; }
    .ref-badge { display:inline-block;margin-top:16px;padding:8px 22px;border:1px solid rgba(255,255,255,.3);border-radius:20px;font-size:13px;letter-spacing:.15em;color:#a8e6c2; }
    .btn-home { display:inline-flex;align-items:center;gap:8px;margin-top:20px;font-family:'DM Sans',sans-serif;font-size:12px;letter-spacing:.2em;text-transform:uppercase;color:#fff;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);padding:12px 28px;border-radius:2px;cursor:pointer;text-decoration:none;transition:background .2s; }
    .btn-home:hover { background:rgba(255,255,255,.25); }
    .tag-pill { display:inline-block;background:#f5f0e8;border-radius:20px;padding:3px 12px;font-size:12px;color:var(--mid);margin:2px; }
    .error-box { background:#fff3f3;border:1.5px solid #e8b4b4;border-radius:4px;padding:14px 18px;margin-bottom:20px;font-size:13.5px;color:#8b2020; }
  </style>
</head>
<body>
<nav>
  <div class="nav-logo">The Grand <span>BLD</span></div>
  <div class="nav-steps">
    <span class="nav-step done"><span class="num">✓</span><span class="label">Guest Info</span></span>
    <div class="nav-divider"></div>
    <span class="nav-step done"><span class="num">✓</span><span class="label">Room</span></span>
    <div class="nav-divider"></div>
    <span class="nav-step done"><span class="num">✓</span><span class="label">Food</span></span>
    <div class="nav-divider"></div>
    <span class="nav-step active"><span class="num">4</span><span class="label">Confirm</span></span>
  </div>
</nav>

<div class="page">
<?php if ($confirmed): ?>
  <div class="confirm-banner">
    <div class="tick">✓</div>
    <h2>You're all set!</h2>
    <p>Reservation saved to the database.<br>We look forward to welcoming you.</p>
    <div class="ref-badge">Ref: <?= htmlspecialchars($refNo) ?></div><br/>
    <a href="guestinfo.php" class="btn-home">Make Another Reservation</a>
  </div>
<?php else: ?>
  <div class="page-head">
    <span class="page-tag">Step 4 of 4</span>
    <h1>Review &amp; <em>confirm</em></h1>
    <p>Please check everything before finalising.</p>
  </div>

  <?php if ($dbError): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($dbError) ?></div>
  <?php endif; ?>

  <div class="card">
    <table class="summary-table">
      <tr><td>Guest</td>    <td><?= htmlspecialchars($g['first_name'].' '.$g['last_name']) ?></td></tr>
      <tr><td>Email</td>    <td><?= htmlspecialchars($g['email']) ?></td></tr>
      <tr><td>Phone</td>    <td><?= htmlspecialchars($g['phone']) ?></td></tr>
      <?php if (!empty($g['nationality'])): ?>
      <tr><td>Nationality</td><td><?= htmlspecialchars($g['nationality']) ?></td></tr>
      <?php endif; ?>
      <tr><td>Check-in</td> <td><?= date('D, d M Y', strtotime($g['check_in'])) ?></td></tr>
      <tr><td>Check-out</td><td><?= date('D, d M Y', strtotime($g['check_out'])) ?></td></tr>
      <tr><td>Guests</td>   <td><?= htmlspecialchars($g['guests']) ?></td></tr>
      <tr><td>Room</td>     <td><?= htmlspecialchars($r['room']) ?> <span style="color:var(--muted);font-size:12px;">(<?= $nights ?> night<?= $nights>1?'s':'' ?> × ₱<?= number_format($roomRate) ?>)</span></td></tr>
      <?php if (!empty($r['bed']) || !empty($r['floor'])): ?>
      <tr><td>Preferences</td><td>
        <?php if($r['bed'])   echo '<span class="tag-pill">'.htmlspecialchars($r['bed']).'</span>'; ?>
        <?php if($r['floor']) echo '<span class="tag-pill">'.htmlspecialchars($r['floor']).'</span>'; ?>
      </td></tr>
      <?php endif; ?>
      <?php if (!empty($r['notes'])): ?>
      <tr><td>Notes</td><td style="font-weight:300;font-size:13px;"><?= htmlspecialchars($r['notes']) ?></td></tr>
      <?php endif; ?>
      <?php if ($foods): ?>
      <tr><td>Dining</td><td>
        <?php foreach($foods as $f) echo '<span class="tag-pill">'.htmlspecialchars($f).'</span>'; ?>
      </td></tr>
      <?php endif; ?>
    </table>
    <div style="margin-top:18px;padding-top:16px;border-top:1px dashed var(--border);">
      <table class="summary-table">
        <tr><td>Room Total</td><td>₱<?= number_format($roomTotal) ?></td></tr>
        <?php if ($foodTotal > 0): ?>
        <tr><td>Dining Total</td><td>₱<?= number_format($foodTotal) ?></td></tr>
        <?php endif; ?>
        <tr class="summary-total"><td>Grand Total</td><td>₱<?= number_format($grandTotal) ?></td></tr>
      </table>
    </div>
    <form method="POST" action="booking.php">
      <div class="btn-row">
        <a href="foods.php" class="btn-back">&larr; Back</a>
        <button type="submit" class="btn-next">✓ &nbsp;Confirm &amp; Save</button>
      </div>
    </form>
  </div>
<?php endif; ?>
</div>
</body>
</html>