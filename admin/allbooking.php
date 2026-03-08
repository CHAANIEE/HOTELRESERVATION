<?php
require_once __DIR__ . '/../db.php';

$pdo = getDB();

// ── Handle POST actions first (before any output) ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // UPDATE STATUS
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['new_status'];
        $resId     = (int)$_POST['res_id'];

        // Fetch reservation + guest info for email
        $row = $pdo->prepare("
            SELECT r.ref_number, r.check_in, r.check_out, r.grand_total,
                   r.status AS old_status,
                   g.first_name, g.last_name, g.email,
                   rm.room_type
            FROM   reservations r
            JOIN   guests g  ON g.id  = r.guest_id
            JOIN   rooms  rm ON rm.id = r.room_id
            WHERE  r.id = :id LIMIT 1
        ");
        $row->execute([':id' => $resId]);
        $res = $row->fetch();

        $pdo->prepare("UPDATE reservations SET status = :s WHERE id = :id")
            ->execute([':s' => $newStatus, ':id' => $resId]);

        // Send status-change email
        if ($res && $res['email']) {
            sendStatusEmail($res, $newStatus);
        }

        header('Location: allbooking.php?' . http_build_query(array_filter([
            'status' => $_GET['status'] ?? '',
            'q'      => $_GET['q']      ?? '',
            'from'   => $_GET['from']   ?? '',
            'to'     => $_GET['to']     ?? '',
        ])));
        exit;
    }

    // DELETE BOOKING
    if (isset($_POST['delete_booking'])) {
        $resId = (int)$_POST['res_id'];

        // Fetch before deleting for the email
        $row = $pdo->prepare("
            SELECT r.ref_number, r.check_in, r.check_out, r.grand_total,
                   g.first_name, g.last_name, g.email,
                   rm.room_type
            FROM   reservations r
            JOIN   guests g  ON g.id  = r.guest_id
            JOIN   rooms  rm ON rm.id = r.room_id
            WHERE  r.id = :id LIMIT 1
        ");
        $row->execute([':id' => $resId]);
        $res = $row->fetch();

        // Cascade deletes reservation_foods via FK ON DELETE CASCADE
        $pdo->prepare("DELETE FROM reservations WHERE id = :id")
            ->execute([':id' => $resId]);

        // Send cancellation/deletion email
        if ($res && $res['email']) {
            sendDeleteEmail($res, $_POST['delete_reason'] ?? '');
        }

        header('Location: allbooking.php?deleted=1');
        exit;
    }

    // SEND CUSTOM MESSAGE
    if (isset($_POST['send_message'])) {
        $resId   = (int)$_POST['res_id'];
        $subject = trim($_POST['msg_subject'] ?? '');
        $body    = trim($_POST['msg_body']    ?? '');

        $row = $pdo->prepare("
            SELECT r.ref_number, g.first_name, g.last_name, g.email
            FROM   reservations r
            JOIN   guests g ON g.id = r.guest_id
            WHERE  r.id = :id LIMIT 1
        ");
        $row->execute([':id' => $resId]);
        $res = $row->fetch();

        if ($res && $subject && $body) {
            sendCustomEmail($res, $subject, $body);
        }

        header('Location: allbooking.php?messaged=1');
        exit;
    }
}

// ── Email helpers ──────────────────────────────────────────
function hotelEmail(): string { return 'no-reply@grandmaison.com'; }
function hotelName():  string { return 'The Grand Maison'; }

function sendStatusEmail(array $res, string $newStatus): void {
    $to      = $res['email'];
    $name    = $res['first_name'] . ' ' . $res['last_name'];
    $ref     = $res['ref_number'];
    $room    = $res['room_type'];
    $ci      = date('D, d M Y', strtotime($res['check_in']));
    $co      = date('D, d M Y', strtotime($res['check_out']));
    $total   = '₱' . number_format($res['grand_total']);
    $hotel   = hotelName();

    $statusLabels = [
        'confirmed'   => 'Confirmed ✅',
        'checked_in'  => 'Checked In 🏨',
        'checked_out' => 'Checked Out 👋',
        'cancelled'   => 'Cancelled ❌',
        'pending'     => 'Pending ⏳',
    ];
    $label = $statusLabels[$newStatus] ?? ucfirst($newStatus);

    $messages = [
        'confirmed'   => "Your reservation has been confirmed. We look forward to welcoming you!",
        'checked_in'  => "You have been checked in. Enjoy your stay at $hotel!",
        'checked_out' => "We hope you enjoyed your stay. Thank you for choosing $hotel. We hope to see you again soon!",
        'cancelled'   => "Your reservation has been cancelled by our team. If you have questions, please contact us.",
        'pending'     => "Your reservation is currently under review. We will update you shortly.",
    ];
    $msg = $messages[$newStatus] ?? "Your reservation status has been updated.";

    $subject = "[$hotel] Reservation $ref — Status: $label";
    $body    = buildEmailHtml($name, $ref, $room, $ci, $co, $total, $label, $msg, $hotel);
    $headers = buildHeaders();

    mail($to, $subject, $body, $headers);
}

function sendDeleteEmail(array $res, string $reason): void {
    $to    = $res['email'];
    $name  = $res['first_name'] . ' ' . $res['last_name'];
    $ref   = $res['ref_number'];
    $room  = $res['room_type'];
    $ci    = date('D, d M Y', strtotime($res['check_in']));
    $co    = date('D, d M Y', strtotime($res['check_out']));
    $total = '₱' . number_format($res['grand_total']);
    $hotel = hotelName();

    $note  = $reason
        ? "Our team noted: <em>" . htmlspecialchars($reason) . "</em><br/><br/>"
        : '';

    $msg   = "Your reservation has been removed from our system. {$note}"
           . "We apologise for any inconvenience. Please contact us if you have any questions.";

    $subject = "[$hotel] Reservation $ref — Removed";
    $body    = buildEmailHtml($name, $ref, $room, $ci, $co, $total, 'Removed 🗑', $msg, $hotel);
    $headers = buildHeaders();

    mail($to, $subject, $body, $headers);
}

function sendCustomEmail(array $res, string $subject, string $msgBody): void {
    $to    = $res['email'];
    $name  = $res['first_name'] . ' ' . $res['last_name'];
    $ref   = $res['ref_number'];
    $hotel = hotelName();

    $html  = '
    <div style="font-family:Georgia,serif;max-width:560px;margin:0 auto;background:#faf7f2;border:1px solid #ddd3be;border-radius:4px;overflow:hidden;">
      <div style="background:#2c1f0e;padding:28px 32px;">
        <p style="color:#c08a3a;font-size:11px;letter-spacing:.3em;text-transform:uppercase;margin:0 0 6px;">The Grand Maison</p>
        <h1 style="color:#fff;font-size:22px;font-weight:400;margin:0;">' . htmlspecialchars($subject) . '</h1>
      </div>
      <div style="padding:32px;">
        <p style="font-size:15px;margin:0 0 16px;">Dear ' . htmlspecialchars($name) . ',</p>
        <div style="font-size:14px;line-height:1.7;color:#3a3020;">' . nl2br(htmlspecialchars($msgBody)) . '</div>
        <p style="margin:24px 0 0;font-size:12px;color:#9c8a72;">Reservation ref: <strong>' . $ref . '</strong></p>
      </div>
      <div style="background:#f0ebe0;padding:16px 32px;text-align:center;font-size:11px;color:#9c8a72;">
        ' . $hotel . ' · reservations@grandmaison.com
      </div>
    </div>';

    mail($to, "[$hotel] " . $subject, $html, buildHeaders());
}

function buildEmailHtml(string $name, string $ref, string $room,
                        string $ci, string $co, string $total,
                        string $statusLabel, string $message, string $hotel): string
{
    return '
    <!DOCTYPE html>
    <html><body style="margin:0;padding:20px;background:#f5f0e8;">
    <div style="font-family:Georgia,serif;max-width:560px;margin:0 auto;background:#faf7f2;border:1px solid #ddd3be;border-radius:4px;overflow:hidden;">
      <div style="background:#2c1f0e;padding:28px 32px;">
        <p style="color:#c08a3a;font-size:11px;letter-spacing:.3em;text-transform:uppercase;margin:0 0 8px;">The Grand Maison</p>
        <h1 style="color:#fff;font-size:24px;font-weight:400;margin:0;">Reservation Update</h1>
      </div>
      <div style="padding:32px;">
        <p style="font-size:15px;margin:0 0 20px;">Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
        <p style="font-size:14px;line-height:1.7;margin:0 0 24px;color:#3a3020;">' . $message . '</p>

        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <tr style="background:#f0ebe0;">
            <td style="padding:10px 14px;color:#9c8a72;text-transform:uppercase;font-size:11px;letter-spacing:.1em;">Reference</td>
            <td style="padding:10px 14px;font-weight:600;color:#c08a3a;">' . $ref . '</td>
          </tr>
          <tr>
            <td style="padding:10px 14px;color:#9c8a72;text-transform:uppercase;font-size:11px;letter-spacing:.1em;border-top:1px solid #e8e0d0;">Status</td>
            <td style="padding:10px 14px;border-top:1px solid #e8e0d0;">' . $statusLabel . '</td>
          </tr>
          <tr style="background:#f0ebe0;">
            <td style="padding:10px 14px;color:#9c8a72;text-transform:uppercase;font-size:11px;letter-spacing:.1em;">Room</td>
            <td style="padding:10px 14px;">' . htmlspecialchars($room) . '</td>
          </tr>
          <tr>
            <td style="padding:10px 14px;color:#9c8a72;text-transform:uppercase;font-size:11px;letter-spacing:.1em;border-top:1px solid #e8e0d0;">Check-in</td>
            <td style="padding:10px 14px;border-top:1px solid #e8e0d0;">' . $ci . '</td>
          </tr>
          <tr style="background:#f0ebe0;">
            <td style="padding:10px 14px;color:#9c8a72;text-transform:uppercase;font-size:11px;letter-spacing:.1em;">Check-out</td>
            <td style="padding:10px 14px;">' . $co . '</td>
          </tr>
          <tr>
            <td style="padding:10px 14px;color:#9c8a72;text-transform:uppercase;font-size:11px;letter-spacing:.1em;border-top:1px solid #e8e0d0;">Total</td>
            <td style="padding:10px 14px;border-top:1px solid #e8e0d0;font-size:16px;font-weight:600;color:#2c1f0e;">' . $total . '</td>
          </tr>
        </table>
      </div>
      <div style="background:#2c1f0e;padding:18px 32px;text-align:center;">
        <p style="color:rgba(255,255,255,.4);font-size:11px;margin:0;">' . $hotel . ' &nbsp;·&nbsp; reservations@grandmaison.com &nbsp;·&nbsp; +63 2 8888 1924</p>
      </div>
    </div>
    </body></html>';
}

function buildHeaders(): string {
    return implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . hotelName() . ' <' . hotelEmail() . '>',
        'Reply-To: reservations@grandmaison.com',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);
}

// ── Filters ────────────────────────────────────────────────
$status   = $_GET['status'] ?? '';
$search   = trim($_GET['q'] ?? '');
$dateFrom = $_GET['from']   ?? '';
$dateTo   = $_GET['to']     ?? '';

// ── Stats ──────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                    AS total,
        SUM(status='confirmed')     AS confirmed,
        SUM(status='checked_in')    AS checked_in,
        SUM(status='checked_out')   AS checked_out,
        SUM(status='cancelled')     AS cancelled,
        fn_total_revenue_all()      AS revenue
    FROM reservations
")->fetch();

// ── Main query ─────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
if ($status)   { $where[] = 'r.status = :status';  $params[':status'] = $status; }
if ($search)   { $where[] = '(g.first_name LIKE :q OR g.last_name LIKE :q OR g.email LIKE :q OR r.ref_number LIKE :q)'; $params[':q'] = "%$search%"; }
if ($dateFrom) { $where[] = 'r.check_in >= :from';  $params[':from'] = $dateFrom; }
if ($dateTo)   { $where[] = 'r.check_out <= :to';   $params[':to']   = $dateTo; }

$stmt = $pdo->prepare("
    SELECT r.id, r.ref_number, r.status,
           r.check_in, r.check_out, r.nights, r.num_guests,
           r.room_total, r.food_total, r.grand_total,
           r.bed_preference, r.floor_preference, r.special_notes, r.created_at,
           g.first_name, g.last_name, g.email, g.phone, g.nationality,
           rm.room_type, rm.price_per_night,
           (SELECT GROUP_CONCAT(fi.name SEPARATOR ', ')
            FROM reservation_foods rf
            JOIN food_items fi ON fi.id = rf.food_item_id
            WHERE rf.reservation_id = r.id) AS food_orders
    FROM reservations r
    JOIN guests g  ON g.id  = r.guest_id
    JOIN rooms  rm ON rm.id = r.room_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

function statusBadge(string $s): string {
    $map = [
        'confirmed'   => ['#1a6b3a','#d4f4e2','Confirmed'],
        'checked_in'  => ['#1a4a8a','#d4e4f8','Checked In'],
        'checked_out' => ['#5a4a1a','#f8f0d4','Checked Out'],
        'cancelled'   => ['#8a1a1a','#f8d4d4','Cancelled'],
        'pending'     => ['#5a5a5a','#ebebeb','Pending'],
    ];
    [$fg, $bg, $label] = $map[$s] ?? ['#333','#eee',ucfirst($s)];
    return "<span style='background:$bg;color:$fg;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.06em;'>$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>All Bookings — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Mulish:wght@300;400;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d0f14;--surface:#13161d;--card:#1a1e28;--border:#252a38;
  --accent:#4f8ef7;--accent2:#f7a24f;--text:#e8ecf4;--muted:#6b7694;
  --green:#2ecc7a;--red:#e05555;--yellow:#f5c842;
}
body{font-family:'Mulish',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:50;}
.topbar-logo{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:var(--accent);letter-spacing:.04em;}
.topbar-logo span{color:var(--text);}
.topbar-right{margin-left:auto;display:flex;gap:10px;align-items:center;}
.badge-live{background:rgba(46,204,122,.15);color:var(--green);border:1px solid rgba(46,204,122,.3);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.1em;}

.wrap{max-width:1400px;margin:0 auto;padding:32px 24px;}

/* TOAST */
.toast{position:fixed;top:72px;right:24px;z-index:200;padding:12px 20px;border-radius:6px;font-size:13px;font-weight:600;animation:slideIn .3s ease;display:flex;align-items:center;gap:10px;}
.toast-success{background:#1a3d2a;border:1px solid var(--green);color:var(--green);}
.toast-danger {background:#3d1a1a;border:1px solid var(--red);color:var(--red);}
@keyframes slideIn{from{transform:translateX(30px);opacity:0}to{transform:none;opacity:1}}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:32px;}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:20px;position:relative;overflow:hidden;transition:transform .2s;}
.stat-card:hover{transform:translateY(-2px);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.stat-card.c-all::before{background:var(--accent);}
.stat-card.c-conf::before{background:var(--green);}
.stat-card.c-in::before{background:var(--accent);}
.stat-card.c-out::before{background:var(--yellow);}
.stat-card.c-rev::before{background:var(--accent2);}
.stat-label{font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;}
.stat-value{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:700;}
.stat-card.c-all .stat-value{color:var(--accent);}
.stat-card.c-conf .stat-value{color:var(--green);}
.stat-card.c-in .stat-value{color:var(--accent);}
.stat-card.c-out .stat-value{color:var(--yellow);}
.stat-card.c-rev .stat-value{color:var(--accent2);font-size:1.4rem;}

/* FILTERS */
.filters{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:16px 20px;margin-bottom:22px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.filters input,.filters select{font-family:'Mulish',sans-serif;font-size:13px;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:4px;padding:9px 13px;outline:none;transition:border-color .2s;}
.filters input:focus,.filters select:focus{border-color:var(--accent);}
.filters input[type="text"]{min-width:200px;}
.filters input[type="date"]{color-scheme:dark;}
.filters select option{background:var(--card);}
.btn-filter{font-family:'Mulish',sans-serif;font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;background:var(--accent);color:#fff;border:none;padding:9px 20px;border-radius:4px;cursor:pointer;}
.btn-reset{background:transparent;border:1px solid var(--border);color:var(--muted);padding:9px 14px;border-radius:4px;cursor:pointer;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;}

/* TABLE */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;}
.table-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.table-head h2{font-family:'Syne',sans-serif;font-size:1rem;font-weight:600;}
.count-badge{font-size:12px;color:var(--muted);}
table{width:100%;border-collapse:collapse;}
thead tr{background:var(--surface);}
th{font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);font-weight:600;padding:11px 14px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:13px 14px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:top;}
tr:last-child td{border-bottom:none;}
tbody tr{transition:background .15s;}
tbody tr:hover{background:rgba(79,142,247,.04);}

.guest-name{font-weight:600;font-size:13.5px;}
.guest-sub{font-size:11.5px;color:var(--muted);margin-top:2px;}
.ref{font-family:'Syne',sans-serif;font-size:12px;color:var(--accent);letter-spacing:.05em;}
.room-pill{background:rgba(247,162,79,.12);color:var(--accent2);padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;}
.dates{font-size:12.5px;line-height:1.7;}
.nights-tag{color:var(--muted);font-size:11px;}
.amount{font-family:'Syne',sans-serif;font-size:13.5px;font-weight:600;}
.food-list{font-size:11.5px;color:var(--muted);max-width:150px;line-height:1.5;}
.no-food{font-size:11px;color:var(--border);font-style:italic;}

/* ACTION BUTTONS */
.action-group{display:flex;flex-direction:column;gap:6px;}
.btn-view{background:rgba(79,142,247,.12);color:var(--accent);border:1px solid rgba(79,142,247,.25);border-radius:4px;padding:6px 12px;font-size:11.5px;cursor:pointer;width:100%;text-align:center;}
.btn-msg {background:rgba(46,204,122,.1);color:var(--green);border:1px solid rgba(46,204,122,.2);border-radius:4px;padding:6px 12px;font-size:11.5px;cursor:pointer;width:100%;text-align:center;}
.btn-del {background:rgba(224,85,85,.1);color:var(--red);border:1px solid rgba(224,85,85,.2);border-radius:4px;padding:6px 12px;font-size:11.5px;cursor:pointer;width:100%;text-align:center;}
.btn-view:hover{background:rgba(79,142,247,.25);}
.btn-msg:hover {background:rgba(46,204,122,.2);}
.btn-del:hover {background:rgba(224,85,85,.2);}

.status-form select{font-family:'Mulish',sans-serif;font-size:11.5px;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:4px;padding:5px 8px;cursor:pointer;outline:none;width:100%;margin-bottom:5px;}
.btn-upd{font-size:10px;font-weight:700;letter-spacing:.1em;background:rgba(79,142,247,.15);color:var(--accent);border:1px solid rgba(79,142,247,.3);border-radius:4px;padding:5px 10px;cursor:pointer;width:100%;}
.btn-upd:hover{background:rgba(79,142,247,.3);}

/* MODALS */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:100;place-items:center;}
.overlay.open{display:grid;}
.modal{background:var(--card);border:1px solid var(--border);border-radius:8px;width:90%;animation:up .3s cubic-bezier(.22,1,.36,1);}
@keyframes up{from{transform:translateY(20px);opacity:0}to{transform:none;opacity:1}}
.modal-sm{max-width:460px;}
.modal-md{max-width:560px;}
.modal-lg{max-width:620px;}
.modal-hd{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.modal-hd h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:600;}
.modal-hd.danger h3{color:var(--red);}
.modal-hd.success h3{color:var(--green);}
.modal-hd.msg-head h3{color:var(--accent2);}
.modal-close{background:transparent;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1;padding:0;}
.modal-body{padding:22px 24px;max-height:65vh;overflow-y:auto;}
.modal-foot{padding:14px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}

/* Detail rows */
.det-row{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px;}
.det-row:last-child{border-bottom:none;}
.det-lbl{width:130px;flex-shrink:0;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.1em;padding-top:1px;}
.det-val{flex:1;line-height:1.5;}

/* Form fields in modal */
.f-group{display:flex;flex-direction:column;gap:7px;margin-bottom:16px;}
.f-group label{font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);}
.f-group input,.f-group textarea,.f-group select{font-family:'Mulish',sans-serif;font-size:13.5px;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:4px;padding:11px 14px;outline:none;transition:border-color .2s;width:100%;}
.f-group input:focus,.f-group textarea:focus{border-color:var(--accent);}
.f-group textarea{min-height:120px;resize:vertical;line-height:1.6;}

/* Buttons */
.btn{font-family:'Mulish',sans-serif;font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;padding:10px 22px;border-radius:4px;cursor:pointer;border:none;transition:opacity .2s;}
.btn:hover{opacity:.85;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-danger{background:var(--red);color:#fff;}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted);}
.btn-orange{background:var(--accent2);color:#1a1a1a;}

.warn-box{background:rgba(224,85,85,.08);border:1px solid rgba(224,85,85,.2);border-radius:4px;padding:14px;font-size:13px;color:var(--red);margin-bottom:18px;line-height:1.6;}

.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
.empty-state .icon{font-size:40px;margin-bottom:14px;opacity:.4;}

@media(max-width:900px){
  .stats{grid-template-columns:repeat(2,1fr);}
  .wrap{padding:16px;}
  table{display:block;overflow-x:auto;}
}
</style>
</head>
<body>

<?php if (isset($_GET['deleted'])): ?>
  <div class="toast toast-danger" id="toast">🗑 Booking deleted & email sent to guest.</div>
<?php elseif (isset($_GET['messaged'])): ?>
  <div class="toast toast-success" id="toast">✉ Message sent to guest.</div>
<?php endif; ?>

<div class="topbar">
  <div class="topbar-logo">Grand <span>BLD Hotel</span></div>
  <span style="font-size:13px;color:var(--muted)">/ Admin Panel</span>
  <div class="topbar-right">
    <span class="badge-live">● LIVE</span>
    <a href="order.php" style="font-size:12px;color:var(--accent2);text-decoration:none;border:1px solid rgba(247,162,79,.3);padding:6px 14px;border-radius:4px;">🍽 Kitchen</a>
  </div>
</div>

<div class="wrap">

  <!-- STATS -->
  <div class="stats">
    <div class="stat-card c-all"><div class="stat-label">Total</div><div class="stat-value"><?= $stats['total'] ?></div></div>
    <div class="stat-card c-conf"><div class="stat-label">Confirmed</div><div class="stat-value"><?= $stats['confirmed'] ?></div></div>
    <div class="stat-card c-in"><div class="stat-label">Checked In</div><div class="stat-value"><?= $stats['checked_in'] ?></div></div>
    <div class="stat-card c-out"><div class="stat-label">Checked Out</div><div class="stat-value"><?= $stats['checked_out'] ?></div></div>
    <div class="stat-card c-rev"><div class="stat-label">Revenue</div><div class="stat-value">₱<?= number_format($stats['revenue']) ?></div></div>
  </div>

  <!-- FILTERS -->
  <form method="GET">
    <div class="filters">
      <input type="text" name="q" placeholder="🔍  Name, email, ref…" value="<?= htmlspecialchars($search) ?>"/>
      <select name="status">
        <option value="">All Statuses</option>
        <?php foreach(['confirmed','checked_in','checked_out','cancelled','pending'] as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>"/>
      <input type="date" name="to"   value="<?= htmlspecialchars($dateTo) ?>"/>
      <button type="submit" class="btn-filter">Apply</button>
      <a href="allbooking.php" class="btn-reset">Reset</a>
    </div>
  </form>

  <!-- TABLE -->
  <div class="table-wrap">
    <div class="table-head">
      <h2>Reservation Records</h2>
      <span class="count-badge"><?= count($rows) ?> record<?= count($rows)!=1?'s':'' ?></span>
    </div>

    <?php if (empty($rows)): ?>
      <div class="empty-state"><div class="icon">📋</div><div>No reservations found.</div></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Ref #</th><th>Guest</th><th>Room</th><th>Dates</th>
          <th>Guests</th><th>Food</th><th>Total</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $i => $row): ?>
        <tr>
          <td>
            <span class="ref"><?= htmlspecialchars($row['ref_number']) ?></span><br/>
            <span style="font-size:10.5px;color:var(--muted)"><?= date('d M Y', strtotime($row['created_at'])) ?></span>
          </td>
          <td>
            <div class="guest-name"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></div>
            <div class="guest-sub"><?= htmlspecialchars($row['email']) ?></div>
            <div class="guest-sub"><?= htmlspecialchars($row['phone']) ?></div>
          </td>
          <td>
            <span class="room-pill"><?= htmlspecialchars($row['room_type']) ?></span>
            <?php if($row['bed_preference']): ?><div class="guest-sub"><?= htmlspecialchars($row['bed_preference']) ?></div><?php endif; ?>
          </td>
          <td class="dates">
            <?= date('d M Y', strtotime($row['check_in'])) ?><br/>
            <span style="color:var(--muted);font-size:11px;">↓</span><br/>
            <?= date('d M Y', strtotime($row['check_out'])) ?><br/>
            <span class="nights-tag"><?= $row['nights'] ?> night<?= $row['nights']>1?'s':'' ?></span>
          </td>
          <td style="text-align:center"><?= $row['num_guests'] ?></td>
          <td>
            <?php if ($row['food_orders']): ?>
              <div class="food-list"><?= htmlspecialchars($row['food_orders']) ?></div>
            <?php else: ?>
              <span class="no-food">None</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="amount">₱<?= number_format($row['grand_total']) ?></div>
            <?php if ($row['food_total'] > 0): ?>
              <div class="guest-sub">Room: ₱<?= number_format($row['room_total']) ?></div>
              <div class="guest-sub">Food: ₱<?= number_format($row['food_total']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="status-form">
              <input type="hidden" name="res_id" value="<?= $row['id'] ?>"/>
              <select name="new_status">
                <?php foreach(['confirmed','checked_in','checked_out','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" name="update_status" class="btn-upd">↑ Update</button>
            </form>
            <div style="margin-top:6px"><?= statusBadge($row['status']) ?></div>
          </td>
          <td>
            <div class="action-group">
              <button class="btn-view" onclick="openDetail(<?= $i ?>)">👁 View</button>
              <button class="btn-msg"  onclick="openMsg(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'].' '.$row['last_name'])) ?>', '<?= htmlspecialchars($row['ref_number']) ?>')">✉ Message</button>
              <button class="btn-del"  onclick="openDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['ref_number']) ?>', '<?= htmlspecialchars(addslashes($row['first_name'].' '.$row['last_name'])) ?>')">🗑 Delete</button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div><!-- /wrap -->

<!-- ═══════════════════════════════════
     DETAIL MODALS
═══════════════════════════════════ -->
<?php foreach ($rows as $i => $row): ?>
<div class="overlay" id="detail-<?= $i ?>">
  <div class="modal modal-lg">
    <div class="modal-hd">
      <h3>📋 <?= htmlspecialchars($row['ref_number']) ?></h3>
      <button class="modal-close" onclick="closeAll()">✕</button>
    </div>
    <div class="modal-body">
      <div class="det-row"><span class="det-lbl">Guest</span><span class="det-val"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></span></div>
      <div class="det-row"><span class="det-lbl">Email</span><span class="det-val"><?= htmlspecialchars($row['email']) ?></span></div>
      <div class="det-row"><span class="det-lbl">Phone</span><span class="det-val"><?= htmlspecialchars($row['phone']) ?></span></div>
      <?php if($row['nationality']): ?><div class="det-row"><span class="det-lbl">Nationality</span><span class="det-val"><?= htmlspecialchars($row['nationality']) ?></span></div><?php endif; ?>
      <div class="det-row"><span class="det-lbl">Room</span><span class="det-val"><?= htmlspecialchars($row['room_type']) ?></span></div>
      <div class="det-row"><span class="det-lbl">Check-in</span><span class="det-val"><?= date('D, d M Y', strtotime($row['check_in'])) ?></span></div>
      <div class="det-row"><span class="det-lbl">Check-out</span><span class="det-val"><?= date('D, d M Y', strtotime($row['check_out'])) ?></span></div>
      <div class="det-row"><span class="det-lbl">Nights</span><span class="det-val"><?= $row['nights'] ?></span></div>
      <div class="det-row"><span class="det-lbl">Guests</span><span class="det-val"><?= $row['num_guests'] ?></span></div>
      <?php if($row['bed_preference']): ?><div class="det-row"><span class="det-lbl">Bed Pref.</span><span class="det-val"><?= htmlspecialchars($row['bed_preference']) ?></span></div><?php endif; ?>
      <?php if($row['floor_preference']): ?><div class="det-row"><span class="det-lbl">Floor Pref.</span><span class="det-val"><?= htmlspecialchars($row['floor_preference']) ?></span></div><?php endif; ?>
      <?php if($row['special_notes']): ?><div class="det-row"><span class="det-lbl">Notes</span><span class="det-val"><?= htmlspecialchars($row['special_notes']) ?></span></div><?php endif; ?>
      <div class="det-row"><span class="det-lbl">Food Orders</span><span class="det-val"><?= $row['food_orders'] ? htmlspecialchars($row['food_orders']) : '<em style="color:var(--muted)">None</em>' ?></span></div>
      <div class="det-row"><span class="det-lbl">Room Total</span><span class="det-val">₱<?= number_format($row['room_total']) ?></span></div>
      <div class="det-row"><span class="det-lbl">Food Total</span><span class="det-val">₱<?= number_format($row['food_total']) ?></span></div>
      <div class="det-row"><span class="det-lbl">Grand Total</span><span class="det-val" style="font-family:'Syne',sans-serif;font-size:1.1rem;color:var(--accent2)">₱<?= number_format($row['grand_total']) ?></span></div>
      <div class="det-row"><span class="det-lbl">Status</span><span class="det-val"><?= statusBadge($row['status']) ?></span></div>
      <div class="det-row"><span class="det-lbl">Booked On</span><span class="det-val"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></span></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeAll()">Close</button>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- ═══════════════════════════════════
     DELETE CONFIRM MODAL
═══════════════════════════════════ -->
<div class="overlay" id="deleteModal">
  <div class="modal modal-sm">
    <div class="modal-hd danger">
      <h3>🗑 Delete Reservation</h3>
      <button class="modal-close" onclick="closeAll()">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <div class="warn-box">
          You are about to permanently delete reservation <strong id="del-ref"></strong> for <strong id="del-guest"></strong>.<br/><br/>
          This cannot be undone. An email will be sent to the guest notifying them.
        </div>
        <input type="hidden" name="res_id" id="del-id"/>
        <div class="f-group">
          <label>Reason for deletion (optional — included in email)</label>
          <textarea name="delete_reason" placeholder="e.g. Duplicate booking, system error, guest requested removal…"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeAll()">Cancel</button>
        <button type="submit" name="delete_booking" class="btn btn-danger">Yes, Delete &amp; Notify Guest</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════
     CUSTOM MESSAGE MODAL
═══════════════════════════════════ -->
<div class="overlay" id="msgModal">
  <div class="modal modal-md">
    <div class="modal-hd msg-head">
      <h3>✉ Send Message to Guest</h3>
      <button class="modal-close" onclick="closeAll()">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:18px;">
          Sending to: <strong id="msg-guest" style="color:var(--text)"></strong>
          &nbsp;·&nbsp; Ref: <span id="msg-ref" style="color:var(--accent)"></span>
        </p>
        <input type="hidden" name="res_id" id="msg-id"/>
        <div class="f-group">
          <label>Subject <span style="color:var(--red)">*</span></label>
          <input type="text" name="msg_subject" placeholder="e.g. Your upcoming stay — important update" required/>
        </div>
        <div class="f-group">
          <label>Message <span style="color:var(--red)">*</span></label>
          <textarea name="msg_body" placeholder="Type your message here…" required></textarea>
        </div>
        <p style="font-size:11.5px;color:var(--muted);">💡 The guest's name, reservation ref, and hotel contact info will be included automatically.</p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeAll()">Cancel</button>
        <button type="submit" name="send_message" class="btn btn-orange">✉ Send Email</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDetail(i) { document.getElementById('detail-'+i).classList.add('open'); }

function openDelete(id, ref, guest) {
  document.getElementById('del-id').value    = id;
  document.getElementById('del-ref').textContent   = ref;
  document.getElementById('del-guest').textContent = guest;
  document.getElementById('deleteModal').classList.add('open');
}

function openMsg(id, guest, ref) {
  document.getElementById('msg-id').value            = id;
  document.getElementById('msg-guest').textContent   = guest;
  document.getElementById('msg-ref').textContent     = ref;
  document.getElementById('msgModal').classList.add('open');
}

function closeAll() {
  document.querySelectorAll('.overlay').forEach(o => o.classList.remove('open'));
}

// Close on backdrop click
document.querySelectorAll('.overlay').forEach(o =>
  o.addEventListener('click', e => { if (e.target === o) closeAll(); })
);

// Auto-hide toast
const toast = document.getElementById('toast');
if (toast) setTimeout(() => toast.style.opacity = '0', 3500);
</script>
</body>
</html>