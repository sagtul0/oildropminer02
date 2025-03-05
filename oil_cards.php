<?php
include 'config.php'; // اتصال به دیتابیس
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// تعریف آرایه‌های هزینه‌ها و پاداش‌ها در ابتدا
$oil_costs = [
    1 => 450, 2 => 1590, 3 => 900, 4 => 1350, 5 => 2700,
    6 => 1500, 7 => 1050, 8 => 1200, 9 => 1650, 10 => 3000,
    11 => 1950, 12 => 2250, 13 => 2550, 14 => 2850, 15 => 3300,
    16 => 3750, 17 => 4050, 18 => 4350, 19 => 4650, 20 => 4950,
    21 => 5250, 22 => 5550, 23 => 5850, 24 => 6150, 25 => 6450,
    26 => 6750, 27 => 7050, 28 => 7350, 29 => 7650, 30 => 7950
];

$ton_costs = [
    1 => 0.15, 2 => 0.36, 3 => 0.24, 4 => 0.45, 5 => 0.60,
    6 => 0.75, 7 => 0.90, 8 => 1.05, 9 => 1.20, 10 => 1.35
];

$ton_rewards = [1 => 300, 2 => 600, 3 => 450, 4 => 750, 5 => 900, 6 => 1050, 7 => 1200, 8 => 1350, 9 => 1500, 10 => 1650];

// گرفتن اطلاعات کاربر از دیتابیس
$stmt = $conn->prepare("SELECT oil_drops, balance, invite_reward FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $oil_drops = (int)$user['oil_drops'];
    $balance = (float)$user['balance']; // بالانس به‌صورت TON
    $referrals = (int)$user['invite_reward']; // تعداد دوستان دعوت‌شده
    error_log("User Data - oil_drops: $oil_drops, balance: $balance, referrals: $referrals"); // لاج برای دیباگ
} else {
    // اگر کاربر پیدا نشد، به صفحه لاگین هدایت کن
    header("Location: login.php");
    exit();
}

// بررسی کارت‌های فعال کاربر برای نمایش دکمه مناسب
$active_cards_stmt = $conn->prepare("SELECT card_id, card_type FROM user_cards WHERE user_id = ?");
$active_cards_stmt->bind_param("i", $user_id);
$active_cards_stmt->execute();
$active_cards_result = $active_cards_stmt->get_result();
$active_cards = [];
while ($row = $active_cards_result->fetch_assoc()) {
    $active_cards[$row['card_type'] . '_' . $row['card_id']] = true;
}
$active_cards_stmt->close();

// پردازش باز کردن کارت‌ها
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $card_id = $_POST['card_id'] ?? '';
    $card_type = $_POST['card_type'] ?? ''; // oil یا ton

    if ($card_type === 'oil') {
        $needs_referral = [5, 10, 15, 20, 25, 30]; // کارت‌هایی که نیاز به دوست جدید دارن

        if (isset($oil_costs[$card_id])) {
            $cost = $oil_costs[$card_id];
            $needs_new_referral = in_array($card_id, $needs_referral);

            error_log("Card ID: $card_id, Cost: $cost, Needs Referral: " . ($needs_new_referral ? 'Yes' : 'No') . ", Referrals: $referrals"); // لاج برای دیباگ

            if ($oil_drops >= $cost && (!$needs_new_referral || $referrals < $card_id)) {
                // کسر قطره نفت از کاربر
                $new_oil = $oil_drops - $cost;
                $update_stmt = $conn->prepare("UPDATE users SET oil_drops = ? WHERE id = ?");
                $update_stmt->bind_param("ii", $new_oil, $user_id);
                if (!$update_stmt->execute()) {
                    error_log("Error updating oil drops: " . $update_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Database error updating oil drops: ' . $update_stmt->error]);
                    exit();
                }
                $update_stmt->close();

                // ثبت کارت در دیتابیس
                $reward = [1 => 150, 2 => 530, 3 => 300, 4 => 450, 5 => 900, 6 => 500, 7 => 350, 8 => 400, 9 => 550, 10 => 1000,
                           11 => 650, 12 => 750, 13 => 850, 14 => 950, 15 => 1100, 16 => 1250, 17 => 1350, 18 => 1450, 19 => 1550, 20 => 1650,
                           21 => 1750, 22 => 1850, 23 => 1950, 24 => 2050, 25 => 2150, 26 => 2250, 27 => 2350, 28 => 2450, 29 => 2550, 30 => 2650][$card_id];
                $insert_card_stmt = $conn->prepare("INSERT INTO user_cards (user_id, card_id, card_type, reward, last_reward_at) VALUES (?, ?, 'oil', ?, NOW())");
                $insert_card_stmt->bind_param("iii", $user_id, $card_id, $reward);
                if (!$insert_card_stmt->execute()) {
                    error_log("Error inserting card: " . $insert_card_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Database error inserting card: ' . $insert_card_stmt->error]);
                    exit();
                }
                $insert_card_stmt->close();

                if ($needs_new_referral) {
                    echo json_encode(['success' => true, 'message' => 'You need to invite a new friend to unlock this card!', 'oil_drops' => $new_oil]);
                } else {
                    echo json_encode(['success' => true, 'message' => "Card unlocked successfully! You will earn $reward oil drops every 8 hours.", 'oil_drops' => $new_oil]);
                }
            } else {
                $error_msg = "Not enough oil drops or referrals to unlock this card. (Oil: $oil_drops, Cost: $cost, Referrals: $referrals)";
                error_log($error_msg);
                echo json_encode(['success' => false, 'message' => $error_msg]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid card ID.']);
        }
    } elseif ($card_type === 'ton') {
        if (isset($ton_costs[$card_id])) {
            $cost = $ton_costs[$card_id];

            if ($balance >= $cost) {
                // کسر TON از بالانس کاربر
                $new_balance = $balance - $cost;
                $update_stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $update_stmt->bind_param("di", $new_balance, $user_id);
                if (!$update_stmt->execute()) {
                    error_log("Error updating balance: " . $update_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Database error updating balance: ' . $update_stmt->error]);
                    exit();
                }
                $update_stmt->close();

                // ثبت کارت در دیتابیس
                $reward = $ton_rewards[$card_id];
                $insert_card_stmt = $conn->prepare("INSERT INTO user_cards (user_id, card_id, card_type, reward, last_reward_at) VALUES (?, ?, 'ton', ?, NOW())");
                $insert_card_stmt->bind_param("iii", $user_id, $card_id, $reward);
                if (!$insert_card_stmt->execute()) {
                    error_log("Error inserting TON card: " . $insert_card_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Database error inserting TON card: ' . $insert_card_stmt->error]);
                    exit();
                }
                $insert_card_stmt->close();

                echo json_encode(['success' => true, 'message' => "TON Card unlocked successfully! You will earn $reward oil drops every 8 hours.", 'balance' => $new_balance]);
            } else {
                $error_msg = "Not enough TON balance to unlock this card. (Balance: $balance, Cost: $cost)";
                error_log($error_msg);
                echo json_encode(['success' => false, 'message' => $error_msg]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid TON card ID.']);
        }
    }
    exit; // جلوگیری از رندر بقیه صفحه بعد از پردازش POST
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Oil Cards - Oil Drop Miner</title>
  <!-- لینک‌های CSS و Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    body {
      background: url('assets/images/backgrounds/auth_background_simple.jpg') no-repeat center center fixed;
      background-size: cover;
      color: #ffffff !important;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      overflow-x: hidden;
    }
    .navbar {
      height: 60px !important;
      background-color: #1a1a1a !important;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
    }
    .navbar-brand { 
      font-size: 1.1rem !important; 
      color: #ffcc00 !important; 
      font-weight: bold; 
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7); 
    }
    .navbar-brand img { 
      width: 50px; /* کاهش اندازه لوگو */
      height: auto;
      margin-right: 5px; /* فاصله بین لوگو و متن */
    }
    .nav-link { font-size: 1rem !important; color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5); }
    .nav-link:hover { color: #ffcc00 !important; }
    .container {
      max-width: 1200px;
      margin-top: 80px !important; /* فاصله از ناوبر */
      padding: 20px;
      position: relative;
      z-index: 1;
    }
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      justify-content: center;
    }
    .tab-pane {
      max-height: calc(100vh - 400px); /* ارتفاع حداکثری برای اسکرول */
      overflow-y: auto; /* اسکرول عمودی برای تب‌ها */
      padding-bottom: 20px; /* فاصله از پایین برای اسکرول بهتر */
    }
    .card {
      background: rgba(30, 30, 30, 0.9);
      border: 2px solid #D4A017;
      border-radius: 15px;
      padding: 15px;
      text-align: center;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s;
      width: 100%; /* عرض کامل برای هر کارت در گرید */
      height: 350px; /* ارتفاع ثابت برای کارت‌ها */
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
    }
    .card:hover {
      transform: scale(1.05);
      box-shadow: 0 5px 15px rgba(212, 160, 23, 0.5);
    }
    .card img { 
      max-width: 100%; 
      height: 150px; /* ارتفاع ثابت برای تصاویر */
      object-fit: cover;
      border-radius: 10px; 
    }
    .card-title { 
      color: #ffcc00; 
      font-weight: bold; 
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7); 
      font-size: 1.1rem;
      margin-top: 10px;
    }
    .card-cost, .card-reward { color: #ffffff; font-size: 0.9rem; }
    .tab-content { margin-top: 20px; }
    #particles-js { 
      position: absolute; 
      top: 0; 
      left: 0; 
      width: 100%; 
      height: 100%; 
      z-index: -1; 
    }
    footer {
      position: fixed !important;
      bottom: 0 !important;
      width: 100% !important;
      background-color: #1a1a1a !important;
      padding: 10px 0 !important;
      z-index: 10; /* افزایش z-index برای اطمینان از نمایش در پایین */
    }
    footer p { 
      color: #ffffff !important; 
      font-size: 0.9rem !important; 
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5); 
    }
    footer a { 
      color: #ffcc00 !important; 
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5); 
    }
    .alert { margin-top: 20px; }
    .btn-warning { background-color: #D4A017; border-color: #D4A017; }
    .btn-warning:hover { background-color: #B89415; border-color: #B89415; }

    /* استایل برای دکمه فعال */
    .card-active {
      background-color: #28a745 !important; /* سبز برای نشان دادن فعال بودن */
      border-color: #1e7e34 !important;
      cursor: default !important;
    }
    .card-active:hover {
      transform: none !important;
      box-shadow: none !important;
      background-color: #218838 !important; /* سبز تیره‌تر در هورور */
      border-color: #1e7e34 !important;
    }
  </style>
</head>
<body>
  <div id="particles-js"></div>
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
      <a class="navbar-brand" href="index.php"><img src="assets/images/oil_drop_logo.png" alt="Oil Drop Miner"> Oil Drop Miner</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
          <?php if (isset($_SESSION['user_id'])): ?>
            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="earn.php">Earn</a></li>
            <li class="nav-item"><a class="nav-link" href="invite.php">Invite</a></li>
            <li class="nav-item"><a class="nav-link" href="plans.php">Plans</a></li>
            <li class="nav-item"><a class="nav-link" href="oil_cards.php">Oil Cards</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
            <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container">
    <h1 class="text-center text-warning mb-4" style="text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);">Oil Cards</h1>
    <ul class="nav nav-tabs" id="oilCardsTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="oil-tab" data-bs-toggle="tab" data-bs-target="#oil" type="button" role="tab" aria-controls="oil" aria-selected="true">Oil Drop Cards</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="ton-tab" data-bs-toggle="tab" data-bs-target="#ton" type="button" role="tab" aria-controls="ton" aria-selected="false">TON Cards</button>
      </li>
      <li class="nav-item" role="payment">
        <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#new" type="button" role="tab" aria-controls="new" aria-selected="false">New Cards</button>
      </li>
    </ul>

    <div class="tab-content" id="oilCardsTabContent">
      <!-- Tab 1: Oil Drop Cards -->
      <div class="tab-pane fade show active" id="oil" role="tabpanel" aria-labelledby="oil-tab">
        <div class="cards-grid">
          <?php
          $oil_card_names = [
              1 => "Oil Rig Booster", 2 => "Refinery Power", 3 => "Oil Tanker Boost", 4 => "Drill Site Energy",
              5 => "Pipeline Power", 6 => "Offshore Platform", 7 => "Storage Tank Boost", 8 => "Gas Flare Power",
              9 => "Seismic Survey", 10 => "Pumpjack Energy", 11 => "Compressor Station", 12 => "Barge Transport",
              13 => "Extraction Facility", 14 => "Refueling Depot", 15 => "Subsea Pipeline", 16 => "Processing Plant",
              17 => "Transport Truck", 18 => "Oil Field Camp", 19 => "Loading Dock", 20 => "Deepwater Rig",
              21 => "Separation Unit", 22 => "Storage Facility", 23 => "Pipeline Network", 24 => "Refinery Tower",
              25 => "Floating Storage", 26 => "Drilling Ship", 27 => "Gas Processing", 28 => "Transport Pipeline",
              29 => "Oil Terminal", 30 => "Mega Refinery"
          ];
          for ($i = 1; $i <= 30; $i++):
            $card_key = 'oil_' . $i;
            $is_active = isset($active_cards[$card_key]);
          ?>
            <div class="card" data-card-id="<?php echo $i; ?>" data-card-type="oil">
              <img src="assets/images/oil_cards/<?php echo htmlspecialchars($oil_card_names[$i]); ?>.jpg" alt="<?php echo $oil_card_names[$i]; ?>">
              <h5 class="card-title"><?php echo $oil_card_names[$i]; ?></h5>
              <p class="card-cost">Cost: <?php echo $oil_costs[$i]; ?> Oil Drops
                  <?php if (in_array($i, [5, 10, 15, 20, 25, 30])) echo " + 1 New Referral"; ?></p>
              <p class="card-reward">Reward: <?php echo [1 => 150, 2 => 530, 3 => 300, 4 => 450, 5 => 900, 6 => 500, 7 => 350, 8 => 400, 9 => 550, 10 => 1000,
                                                        11 => 650, 12 => 750, 13 => 850, 14 => 950, 15 => 1100, 16 => 1250, 17 => 1350, 18 => 1450, 19 => 1550, 20 => 1650,
                                                        21 => 1750, 22 => 1850, 23 => 1950, 24 => 2050, 25 => 2150, 26 => 2250, 27 => 2350, 28 => 2450, 29 => 2550, 30 => 2650][$i]; ?> Oil Drops / 8h</p>
              <?php if ($is_active): ?>
                <button class="btn btn-warning card-active" disabled>Active</button>
              <?php else: ?>
                <button class="btn btn-warning" onclick="unlockCard(<?php echo $i; ?>, 'oil')">Unlock</button>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Tab 2: TON Cards -->
      <div class="tab-pane fade" id="ton" role="tabpanel" aria-labelledby="ton-tab">
        <div class="cards-grid">
          <?php
          $ton_card_names = [
              1 => "TON Rig Boost", 2 => "TON Refinery Power", 3 => "TON Tanker Surge", 4 => "TON Drill Power",
              5 => "TON Pipeline Boost", 6 => "TON Offshore Boost", 7 => "TON Storage Surge", 8 => "TON Flare Power",
              9 => "TON Seismic Boost", 10 => "TON Pumpjack Surge"
          ];
          for ($i = 1; $i <= 10; $i++):
            $card_key = 'ton_' . $i;
            $is_active = isset($active_cards[$card_key]);
          ?>
            <div class="card" data-card-id="<?php echo $i; ?>" data-card-type="ton">
              <img src="assets/images/oil_cards/<?php echo htmlspecialchars($ton_card_names[$i]); ?>.jpg" alt="<?php echo $ton_card_names[$i]; ?>">
              <h5 class="card-title"><?php echo $ton_card_names[$i]; ?></h5>
              <p class="card-cost">Cost: <?php echo $ton_costs[$i]; ?> TON</p>
              <p class="card-reward">Reward: <?php echo $ton_rewards[$i]; ?> Oil Drops / 8h</p>
              <?php if ($is_active): ?>
                <button class="btn btn-warning card-active" disabled>Active</button>
              <?php else: ?>
                <button class="btn btn-warning" onclick="unlockCard(<?php echo $i; ?>, 'ton')">Unlock</button>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Tab 3: New Cards -->
      <div class="tab-pane fade" id="new" role="tabpanel" aria-labelledby="new-tab">
        <div class="cards-grid">
          <p class="text-center text-white">New cards will be added here as updates are released!</p>
        </div>
      </div>
    </div>

    <?php if (isset($message)): ?>
      <div class="alert alert-<?php echo strpos($message, 'success') !== false ? 'success' : 'danger'; ?> text-center"><?php echo $message; ?></div>
    <?php endif; ?>
  </div>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
  <script>
    particlesJS("particles-js", {
      "particles": {
        "number": { "value": 50, "density": { "enable": true, "value_area": 800 } },
        "color": { "value": "#D4A017" },
        "shape": { "type": "line", "stroke": { "width": 2, "color": "#D4A017" } },
        "opacity": { "value": 0.8, "random": true, "anim": { "enable": true, "speed": 1, "opacity_min": 0.5 } },
        "size": { "value": 0 },
        "line_linked": { "enable": true, "distance": 150, "color": "#D4A017", "opacity": 0.8, "width": 2 },
        "move": { "enable": true, "speed": 2, "direction": "random", "random": true, "straight": false, "out_mode": "out", "bounce": false, "attract": { "enable": false } }
      },
      "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "repulse" }, "onclick": { "enable": false } } },
      "retina_detect": true
    });

    function unlockCard(cardId, cardType) {
      if (confirm(`Are you sure you want to unlock this ${cardType === 'oil' ? 'Oil Drop' : 'TON'} Card?`)) {
        fetch('oil_cards.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `card_id=${encodeURIComponent(cardId)}&card_type=${encodeURIComponent(cardType)}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            const oilCount = document.getElementById('oil-count');
            if (oilCount && data.oil_drops !== undefined) {
              oilCount.textContent = data.oil_drops;
            }
            const balanceText = document.querySelector('.balance-text');
            if (balanceText && data.balance !== undefined) {
              balanceText.textContent = numberFormat(data.balance, 2) + ' TON';
            }
            location.reload(); // رفرش صفحه برای به‌روزرسانی
          } else {
            alert(data.message);
            if (data.message.includes('Database error')) {
              console.error('Database Error:', data.message);
            }
          }
        })
        .catch(error => {
          console.error('Fetch Error:', error);
          alert('An error occurred while unlocking the card. Please check the console for details.');
        });
      }
    }

    // تابع فرمت‌دهی عدد (برای نمایش بالانس)
    function numberFormat(number, decimals) {
      return number.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
  </script>
</body>
</html>