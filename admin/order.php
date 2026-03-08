<?php
require_once __DIR__ . '/../db.php';
$pdo = getDB();

// ── Filter: today / upcoming / all ─────────────
$view   = $_GET['view']   ?? 'today';
$cat    = $_GET['cat']    ?? '';
$search = trim($_GET['q'] ?? '');

$dateFilter = match($view) {
    'today'    => 'AND r.check_in = CURDATE()',
    'upcoming' => 'AND r.check_in > CURDATE() AND r.check_in <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)',
    default    => '',
};

$catFilter  = $cat    ? "AND fi.category = :cat" : '';
$srchFilter = $search ? "AND (g.first_name LIKE :q OR g.last_name LIKE :q OR r.ref_number LIKE :q)" : '';

// ── Orders grouped by reservation ──────────────
$sql = "
    SELECT
        r.ref_number, r.check_in, r.check_out, r.num_guests, r.status, r.special_notes,
        g.first_name, g.last_name, g.phone,
        rm.room_type,
        fi.category, fi.name AS food_name, fi.description AS food_desc,
        rf.quantity, rf.unit_price,
        (rf.quantity * rf.unit_price) AS line_total
    FROM reservation_foods rf
    JOIN reservations r  ON r.id  = rf.reservation_id
    JOIN guests       g  ON g.id  = r.guest_id
    JOIN rooms        rm ON rm.id = r.room_id
    JOIN food_items   fi ON fi.id = rf.food_item_id
    WHERE r.status NOT IN ('cancelled')
    $dateFilter
    $catFilter
    $srchFilter
    ORDER BY r.check_in ASC, fi.category ASC, r.ref_number ASC
";

$params = [];
if ($cat)    $params[':cat'] = $cat;
if ($search) $params[':q']   = "%$search%";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawRows = $stmt->fetchAll();

// ── Group by reservation ───────────────────────
$reservations = [];
foreach ($rawRows as $row) {
    $ref = $row['ref_number'];
    if (!isset($reservations[$ref])) {
        $reservations[$ref] = [
            'ref'       => $ref,
            'check_in'  => $row['check_in'],
            'check_out' => $row['check_out'],
            'guests'    => $row['num_guests'],
            'status'    => $row['status'],
            'notes'     => $row['special_notes'],
            'guest'     => $row['first_name'].' '.$row['last_name'],
            'phone'     => $row['phone'],
            'room'      => $row['room_type'],
            'items'     => [],
        ];
    }
    $reservations[$ref]['items'][] = [
        'category'  => $row['category'],
        'name'      => $row['food_name'],
        'desc'      => $row['food_desc'],
        'qty'       => $row['quantity'],
        'price'     => $row['unit_price'],
        'total'     => $row['line_total'],
    ];
}

// ── Summary: items needed today ────────────────
$summarySQL = "
    SELECT fi.category, fi.name, SUM(rf.quantity) AS total_qty
    FROM reservation_foods rf
    JOIN reservations r ON r.id = rf.reservation_id
    JOIN food_items fi  ON fi.id = rf.food_item_id
    WHERE r.status NOT IN ('cancelled')
    $dateFilter
    GROUP BY fi.category, fi.name
    ORDER BY fi.category, total_qty DESC
";
$summaryStmt = $pdo->prepare($summarySQL);
$summaryStmt->execute();
$summary = $summaryStmt->fetchAll();

// Group summary by category
$summaryByCat = [];
foreach ($summary as $s) {
    $summaryByCat[$s['category']][] = $s;
}

// Category emojis
$catIcons = [
    'Breakfast'         => '🌄',
    'Lunch'             => '☀️',
    'Dinner'            => '🌙',
    'Desserts & Drinks' => '🍰',
];

$allCats = $pdo->query("SELECT DISTINCT category FROM food_items ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Kitchen Orders — Chef View</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Mulish:wght@300;400;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0e1008;--surface:#131508;--card:#1a1c0f;--border:#2a2e18;
  --gold:#d4a843;--gold2:#f0c96a;--green:#7ec85a;--red:#e05555;
  --blue:#5ab3e0;--text:#eef0e0;--muted:#7a7d60;
}
body{font-family:'Mulish',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

/* TOP BAR */
.topbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:0 32px;height:60px;display:flex;align-items:center;gap:16px;
  position:sticky;top:0;z-index:50;
}
.topbar-logo{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:var(--gold);letter-spacing:.04em;}
.topbar-logo span{color:var(--text);}
.chef-tag{background:rgba(212,168,67,.15);color:var(--gold2);border:1px solid rgba(212,168,67,.25);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.1em;}
.topbar-right{margin-left:auto;display:flex;gap:10px;align-items:center;}
.btn-admin{font-size:12px;color:var(--blue);text-decoration:none;border:1px solid rgba(90,179,224,.3);padding:6px 14px;border-radius:4px;}
.btn-admin:hover{background:rgba(90,179,224,.08);}
.time-display{font-family:'Syne',sans-serif;font-size:14px;color:var(--gold);opacity:.7;}

/* LAYOUT */
.wrap{max-width:1300px;margin:0 auto;padding:28px 24px;}
.two-col{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;}

/* VIEW TABS */
.view-tabs{display:flex;gap:0;margin-bottom:24px;border:1px solid var(--border);border-radius:6px;overflow:hidden;width:fit-content;}
.view-tab{
  font-family:'Mulish',sans-serif;font-size:12px;font-weight:600;
  letter-spacing:.1em;text-transform:uppercase;
  padding:10px 20px;text-decoration:none;color:var(--muted);
  background:var(--surface);transition:all .2s;border:none;cursor:pointer;
}
.view-tab.active,.view-tab:hover{background:var(--card);color:var(--gold2);}
.view-tab.active{color:var(--gold);box-shadow:inset 0 -2px 0 var(--gold);}

/* FILTERS */
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
.filters select,.filters input{
  font-family:'Mulish',sans-serif;font-size:13px;
  background:var(--surface);border:1px solid var(--border);
  color:var(--text);border-radius:4px;padding:8px 12px;outline:none;
  transition:border-color .2s;
}
.filters select:focus,.filters input:focus{border-color:var(--gold);}
.filters select option{background:var(--card);}
.btn-go{background:var(--gold);color:var(--bg);border:none;padding:8px 18px;border-radius:4px;font-size:12px;font-weight:700;cursor:pointer;letter-spacing:.08em;}

/* ORDER CARDS */
.order-card{
  background:var(--card);border:1px solid var(--border);border-radius:6px;
  margin-bottom:16px;overflow:hidden;
  transition:border-color .2s;
}
.order-card:hover{border-color:rgba(212,168,67,.3);}

.order-card-head{
  padding:14px 18px;background:rgba(212,168,67,.06);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:14px;flex-wrap:wrap;
}
.order-ref{font-family:'Syne',sans-serif;font-size:13px;color:var(--gold);font-weight:600;letter-spacing:.05em;}
.order-guest{font-size:13px;font-weight:600;}
.order-room{background:rgba(212,168,67,.12);color:var(--gold2);padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;}
.order-date{font-size:12px;color:var(--muted);}
.order-guests-badge{background:rgba(126,200,90,.12);color:var(--green);padding:3px 8px;border-radius:4px;font-size:11px;}
.order-status{margin-left:auto;}

.order-items{padding:14px 18px;}
.category-header{
  font-size:10px;letter-spacing:.25em;text-transform:uppercase;
  color:var(--gold);margin:12px 0 8px;padding-bottom:6px;
  border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;
}
.category-header:first-child{margin-top:0;}

.item-row{
  display:flex;align-items:flex-start;gap:12px;
  padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04);
}
.item-row:last-child{border-bottom:none;}
.item-qty{
  width:28px;height:28px;border-radius:50%;
  background:rgba(212,168,67,.15);color:var(--gold2);
  display:grid;place-items:center;font-family:'Syne',sans-serif;
  font-size:13px;font-weight:700;flex-shrink:0;
}
.item-info{flex:1;}
.item-name{font-size:13.5px;font-weight:600;}
.item-desc{font-size:11.5px;color:var(--muted);margin-top:2px;}

.special-notes{
  margin:0 18px 14px;
  padding:10px 14px;
  background:rgba(90,179,224,.06);
  border:1px solid rgba(90,179,224,.15);
  border-radius:4px;
  font-size:12.5px;color:var(--blue);
  display:flex;gap:8px;align-items:flex-start;
}

/* STATUS BADGES */
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.06em;}
.badge-confirmed{background:rgba(126,200,90,.15);color:var(--green);}
.badge-checked_in{background:rgba(90,179,224,.15);color:var(--blue);}
.badge-checked_out{background:rgba(212,168,67,.15);color:var(--gold2);}

/* SUMMARY SIDEBAR */
.sidebar-card{
  background:var(--card);border:1px solid var(--border);border-radius:6px;
  overflow:hidden;position:sticky;top:80px;
}
.sidebar-head{
  padding:16px 18px;background:rgba(212,168,67,.08);
  border-bottom:1px solid var(--border);
}
.sidebar-head h3{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:600;}
.sidebar-head p{font-size:11.5px;color:var(--muted);margin-top:3px;}

.summary-cat{margin:0;padding:0;}
.summary-cat-title{
  font-size:10px;letter-spacing:.2em;text-transform:uppercase;
  color:var(--gold);padding:12px 16px 6px;
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:6px;
}
.summary-item{
  display:flex;justify-content:space-between;align-items:center;
  padding:9px 16px;border-bottom:1px solid rgba(255,255,255,.03);
  font-size:12.5px;
}
.summary-item:last-child{border-bottom:none;}
.s-qty{
  font-family:'Syne',sans-serif;font-weight:700;
  background:rgba(212,168,67,.15);color:var(--gold2);
  padding:2px 8px;border-radius:4px;font-size:12px;
}
.no-orders{
  text-align:center;padding:50px 20px;color:var(--muted);
}
.no-orders .icon{font-size:36px;margin-bottom:12px;opacity:.4;}
.print-btn{
  width:100%;margin-top:0;padding:13px;
  background:var(--gold);color:var(--bg);
  border:none;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;cursor:pointer;
  transition:opacity .2s;
}
.print-btn:hover{opacity:.85;}

@media print{
  .topbar,.filters,.view-tabs,.sidebar-card,.btn-admin,.print-btn{display:none!important;}
  .two-col{grid-template-columns:1fr;}
  body{background:#fff;color:#000;}
  .order-card{border:1px solid #ccc;break-inside:avoid;}
}
@media(max-width:900px){
  .two-col{grid-template-columns:1fr;}
  .wrap{padding:16px;}
}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-logo">Grand <span>BLD Hotel</span></div>
  <span class="chef-tag">🍳 KITCHEN</span>
  <div class="topbar-right">
    <span class="time-display" id="clock"></span>
    <a href="allbooking.php" class="btn-admin">📋 Admin Panel</a>
  </div>
</div>

<div class="wrap">

  <!-- TABS -->
  <div class="view-tabs">
    <a href="?view=today<?= $cat?"&cat=$cat":'' ?>" class="view-tab <?= $view==='today'?'active':'' ?>">Today</a>
    <a href="?view=upcoming<?= $cat?"&cat=$cat":'' ?>" class="view-tab <?= $view==='upcoming'?'active':'' ?>">Next 3 Days</a>
    <a href="?view=all<?= $cat?"&cat=$cat":'' ?>" class="view-tab <?= $view==='all'?'active':'' ?>">All</a>
  </div>

  <!-- FILTERS -->
  <form method="GET">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>"/>
    <div class="filters">
      <select name="cat">
        <option value="">All Categories</option>
        <?php foreach($allCats as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $cat===$c?'selected':'' ?>><?= ($catIcons[$c]??'') .' '. $c ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" placeholder="Search guest / ref…" value="<?= htmlspecialchars($search) ?>"/>
      <button type="submit" class="btn-go">Filter</button>
    </div>
  </form>

  <div class="two-col">

    <!-- LEFT: ORDER CARDS -->
    <div>
      <?php if (empty($reservations)): ?>
        <div class="no-orders">
          <div class="icon">🍽</div>
          <div>No food orders for this period.</div>
        </div>
      <?php else: ?>
        <?php foreach ($reservations as $res): ?>
          <?php
          // Group items by category
          $byCategory = [];
          foreach ($res['items'] as $item) {
              $byCategory[$item['category']][] = $item;
          }
          $statusClass = 'badge-'.str_replace(' ','_',$res['status']);
          ?>
          <div class="order-card">
            <div class="order-card-head">
              <span class="order-ref"><?= htmlspecialchars($res['ref']) ?></span>
              <span class="order-guest"><?= htmlspecialchars($res['guest']) ?></span>
              <span class="order-room"><?= htmlspecialchars($res['room']) ?></span>
              <span class="order-date">Check-in: <strong><?= date('d M Y', strtotime($res['check_in'])) ?></strong></span>
              <span class="order-guests-badge">👤 <?= $res['guests'] ?> guest<?= $res['guests']>1?'s':'' ?></span>
              <span class="order-status"><span class="badge <?= $statusClass ?>"><?= ucwords(str_replace('_',' ',$res['status'])) ?></span></span>
            </div>

            <div class="order-items">
              <?php foreach ($byCategory as $catName => $items): ?>
                <div class="category-header">
                  <?= $catIcons[$catName] ?? '🍴' ?> <?= htmlspecialchars($catName) ?>
                </div>
                <?php foreach ($items as $item): ?>
                  <div class="item-row">
                    <div class="item-qty"><?= $item['qty'] ?></div>
                    <div class="item-info">
                      <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                      <?php if ($item['desc']): ?>
                        <div class="item-desc"><?= htmlspecialchars($item['desc']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>

            <?php if ($res['notes']): ?>
              <div class="special-notes">
                ⚠ <span><strong>Special request:</strong> <?= htmlspecialchars($res['notes']) ?></span>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- RIGHT: SUMMARY SIDEBAR -->
    <div class="sidebar-card">
      <div class="sidebar-head">
        <h3>📦 Prep Summary</h3>
        <p>Total items to prepare <?= $view==='today'?'today':($view==='upcoming'?'(next 3 days)':'(all)') ?></p>
      </div>

      <?php if (empty($summaryByCat)): ?>
        <div style="padding:30px;text-align:center;color:var(--muted);font-size:13px;">No orders.</div>
      <?php else: ?>
        <?php foreach ($summaryByCat as $catName => $items): ?>
          <div class="summary-cat-title"><?= $catIcons[$catName] ?? '🍴' ?> <?= htmlspecialchars($catName) ?></div>
          <?php foreach ($items as $item): ?>
            <div class="summary-item">
              <span style="font-size:12.5px"><?= htmlspecialchars($item['name']) ?></span>
              <span class="s-qty">×<?= $item['total_qty'] ?></span>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <button class="print-btn" onclick="window.print()">🖨 Print Order Sheet</button>
    </div>

  </div>
</div>

<script>
// Live clock
function tick() {
  const now = new Date();
  document.getElementById('clock').textContent =
    now.toLocaleTimeString('en-PH', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
tick(); setInterval(tick, 1000);
</script>
</body>
</html>