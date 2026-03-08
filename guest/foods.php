<?php
session_start();
if (empty($_SESSION['guest'])) { header('Location: guestinfo.php'); exit; }
if (empty($_SESSION['room']))  { header('Location: rooms.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $_SESSION['food'] = $_POST['food'] ?? [];
  header('Location: booking.php');
  exit;
}
$selected = $_SESSION['food'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dining — Grand BLD Hotel</title>
  <link rel="stylesheet" href="../style.css"/>
</head>
<body>

<nav>
  <div class="nav-logo">The Grand <span>BLD</span></div>
  <div class="nav-steps">
    <a href="guestinfo.php" class="nav-step done">
      <span class="num">✓</span><span class="label">Guest Info</span>
    </a>
    <div class="nav-divider"></div>
    <a href="rooms.php" class="nav-step done">
      <span class="num">✓</span><span class="label">Room</span>
    </a>
    <div class="nav-divider"></div>
    <a href="foods.php" class="nav-step active">
      <span class="num">3</span><span class="label">Food</span>
    </a>
    <div class="nav-divider"></div>
    <span class="nav-step">
      <span class="num">4</span><span class="label">Confirm</span>
    </span>
  </div>
</nav>

<div class="page">
  <div class="page-head">
    <span class="page-tag">Step 3 of 4</span>
    <h1>Dining &amp; <em>extras</em></h1>
    <p>Pre-order meals for your stay. Items will be ready upon check-in or delivered to your room.</p>
  </div>

  <div class="card">
    <form method="POST" action="foods.php">

      <?php
      $menu = [
        'Breakfast' => '🌄',
        'items_breakfast' => [
          ['Filipino Breakfast Set',   '🍳', '₱350', 'Sinangag, itlog, longganisa & coffee'],
          ['Continental Breakfast',    '🥐', '₱280', 'Croissant, jam, fresh fruit, OJ'],
          ['American Breakfast',       '🥞', '₱420', 'Pancakes, bacon, eggs, maple syrup'],
          ['Healthy Granola Bowl',     '🥣', '₱220', 'Oats, fresh berries, honey, milk'],
        ],
        'Lunch' => '☀️',
        'items_lunch' => [
          ['Kare-Kare Platter',        '🍲', '₱580', 'Oxtail stew, shrimp paste, puso ng saging'],
          ['Grilled Sea Bass',         '🐟', '₱650', 'Lemon herb, capers, seasonal greens'],
          ['Caesar Salad & Sandwich',  '🥗', '₱390', 'Romaine, croutons, parmesan, chicken'],
          ['Pasta Aglio e Olio',       '🍝', '₱440', 'Garlic, olive oil, chili flakes, parsley'],
        ],
        'Dinner' => '🌙',
        'items_dinner' => [
          ['Wagyu Beef Steak',         '🥩', '₱1,850', '200g A4 wagyu, truffle butter, fries'],
          ['Seafood Platter',          '🦞', '₱1,200', 'Prawns, mussels, squid, garlic sauce'],
          ['Chicken Inasal',           '🍗', '₱480', 'Char-grilled, java rice, atchara'],
          ['Vegetarian Tasting Menu',  '🥦', '₱690', '4-course plant-based dining experience'],
        ],
        'Desserts & Drinks' => '🍰',
        'items_desserts' => [
          ['Leche Flan',              '🍮', '₱180', 'Classic creamy caramel custard'],
          ['Mango Float',             '🥭', '₱210', 'Layers of cream, graham, fresh mango'],
          ['Sparkling Wine Bottle',   '🍾', '₱1,400', 'Chilled upon check-in, with 2 glasses'],
          ['Fresh Fruit Basket',      '🍇', '₱350', 'Seasonal tropical fruits, daily refreshed'],
        ],
      ];

      $sections = [
        'Breakfast'         => ['icon'=>'🌄', 'key'=>'items_breakfast'],
        'Lunch'             => ['icon'=>'☀️', 'key'=>'items_lunch'],
        'Dinner'            => ['icon'=>'🌙', 'key'=>'items_dinner'],
        'Desserts & Drinks' => ['icon'=>'🍰', 'key'=>'items_desserts'],
      ];

      foreach ($sections as $title => $meta):
        $items = $menu[$meta['key']];
      ?>
      <div class="food-section">
        <h3><?= $meta['icon'] ?> <?= $title ?></h3>
        <div class="food-grid">
          <?php foreach ($items as [$name, $emoji, $price, $desc]):
            $val = $name;
            $isSel = in_array($val, $selected);
          ?>
          <div class="food-item <?= $isSel ? 'selected' : '' ?>" onclick="toggleFood(this)">
            <input type="checkbox" name="food[]" value="<?= htmlspecialchars($val) ?>" <?= $isSel ? 'checked':'' ?>>
            <span class="food-emoji"><?= $emoji ?></span>
            <div class="food-details">
              <div class="food-name"><?= $name ?></div>
              <div class="food-price"><?= $price ?></div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px;font-weight:300;"><?= $desc ?></div>
            </div>
            <span class="food-check">✓</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <div class="btn-row">
        <a href="rooms.php" class="btn-back">&larr; Back</a>
        <button type="submit" class="btn-next">Review Reservation &rarr;</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleFood(el) {
  el.classList.toggle('selected');
  const cb = el.querySelector('input[type="checkbox"]');
  cb.checked = !cb.checked;
}
</script>
</body>
</html>