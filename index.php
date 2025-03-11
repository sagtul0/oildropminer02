<?php
include 'header.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['chat_id'])) {
    if (isset($_GET['tgWebAppData'])) {
        $tgData = json_decode($_GET['tgWebAppData'], true);
        $chat_id = $tgData['user']['id'] ?? null;
        if ($chat_id) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE chat_id = ?");
            $stmt->bind_param("i", $chat_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['chat_id'] = $chat_id;
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>User not registered in bot!</div>";
                include 'footer.php';
                exit();
            }
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Please <a href='login_web.php'>login</a> with TON address.</div>";
            include 'footer.php';
            exit();
        }
    } else {
        echo "<div class='alert alert-danger text-center mt-3'>Please <a href='login_web.php'>login</a> with TON address.</div>";
        include 'footer.php';
        exit();
    }
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['chat_id'];

// Check if user is blocked
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user['is_blocked']) {
    echo "<div class='alert alert-danger text-center mt-3'>Your account has been blocked.</div>";
    include 'footer.php';
    exit;
}
$stmt->close();

$stmt = $conn->prepare("SELECT oil_drops, today_clicks, last_click_day, boost_multiplier, auto_clicker FROM users WHERE id=? OR chat_id=?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    error_log("User not found for ID/Chat_ID: $user_id");
    echo "<div class='alert alert-danger text-center mt-3'>User not found!</div>";
    include 'footer.php';
    exit();
}
$user = $res->fetch_assoc();
$oil_drops = (int)$user['oil_drops'];
$today_clicks = (int)$user['today_clicks'];
$last_click_day = $user['last_click_day'];
$boost_multiplier = (float)($user['boost_multiplier'] ?? 1.0);
$auto_clicker = (bool)$user['auto_clicker'];

error_log("User data - oil_drops: $oil_drops, today_clicks: $today_clicks, last_click_day: $last_click_day, boost_multiplier: $boost_multiplier, auto_clicker: $auto_clicker");

// بررسی ریست کلیک‌های روزانه (برای Web App)
$today = date('Y-m-d');
if ($last_click_day !== $today) {
    $stmt = $conn->prepare("UPDATE users SET today_clicks = 0, last_click_day = ? WHERE id = ? OR chat_id = ?");
    $stmt->bind_param("sii", $today, $user_id, $user_id);
    $stmt->execute();
    $today_clicks = 0;
}

// پردازش کلیک برای ماین (فقط در Web App)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mine']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (!isset($_SESSION['chat_id'])) {
        echo "<div class='alert alert-warning text-center mt-3'>Mining can only be done via Telegram Web App!</div>";
    } else {
        if ($today_clicks >= 1000) {
            echo "<div class='alert alert-danger text-center mt-3'>Daily click limit reached!</div>";
        } else {
            $click_value = 1 * $boost_multiplier;
            $new_oil_drops = $oil_drops + $click_value;
            $new_clicks = $today_clicks + 1;

            $stmt = $conn->prepare("UPDATE users SET oil_drops = ?, today_clicks = ? WHERE id = ? OR chat_id = ?");
            $stmt->bind_param("iiii", $new_oil_drops, $new_clicks, $user_id, $user_id);
            if ($stmt->execute()) {
                $oil_drops = $new_oil_drops;
                $today_clicks = $new_clicks;
                echo "<div class='alert alert-success text-center mt-3'>Mined $click_value Oil Drops!</div>";
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>Error mining: " . $stmt->error . "</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Home - Oil Drop Miner</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .home-text { color: #ffffff !important; font-weight: bold !important; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8) !important; font-size: 1.1rem !important; }
    .home-text.warning { color: #ffcc00 !important; }
    .welcome-text { color: #ffcc00 !important; font-weight: bold !important; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8) !important; }
    .alert { color: #000000 !important; text-shadow: none !important; }
    .alert-success { background-color: #d4edda !important; border-color: #c3e6cb !important; }
    .alert-danger { background-color: #f8d7da !important; border-color: #f5c6cb !important; }
    .wallet-form { margin-top: 10px; max-width: 300px; margin-left: auto; margin-right: auto; }
    .wallet-input { background-color: #333 !important; border: 1px solid #b8860b !important; color: #ffffff !important; transition: all 0.3s ease; font-size: 0.9rem !important; }
    .wallet-input:focus { border-color: #daa520 !important; box-shadow: 0 0 8px rgba(218, 165, 32, 0.5) !important; background-color: #444 !important; }
    .btn-wallet { background-color: #b8860b !important; border-color: #b8860b !important; color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; font-size: 0.9rem !important; padding: 6px 12px !important; }
    .btn-wallet:hover { background-color: #daa520 !important; transform: scale(1.05) !important; }
    .container { max-width: 1000px; margin-top: 20px !important; padding-top: 0 !important; }
    .text-success, .text-danger { color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; }
    .fw-bold { color: #ffcc00 !important; }
  </style>
</head>
<body>
  <div id="particles-js"></div>

  <div class="container mt-5 text-center">
    <h2 class="mb-3"><span class="welcome-text">Welcome to</span> <span style="color: #ffcc00;">Oil Drop Miner</span></h2>
    <p><span class="home-text warning">Your Oil Drops:</span> <span id="oil-count" class="fw-bold"><?= $oil_drops; ?></span></p>
    <p><span class="home-text warning">Current Boost:</span> <span class="fw-bold"><?= number_format($boost_multiplier, 1); ?>×</span></p>
    <p><span class="home-text">Auto Clicker:</span> <span class="text-<?= $auto_clicker ? 'success' : 'danger' ?> fw-bold"><?= $auto_clicker ? 'Active ✅' : 'Inactive ❌' ?></span></p>
    <p><span class="home-text warning">Clicks Left Today:</span> <span id="clicks-left" class="fw-bold"><?= 1000 - $today_clicks; ?></span></p>

    <div class="mine-button-container">
      <?php if (!isset($_SESSION['chat_id'])): ?>
        <img id="mine-btn" src="assets/images/oil_drop_logo.png" alt="Mine Oil" class="mine-image" style="opacity: 0.5; cursor: not-allowed;">
        <p class="text-warning mt-2">Mining can only be done via Telegram Web App.</p>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <button type="submit" name="mine" id="mine-btn" class="mine-image" style="border: none; background: none; padding: 0;">
            <img src="assets/images/oil_drop_logo.png" alt="Mine Oil" class="mine-image">
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script>
    particlesJS("particles-js", {
      "particles": { "number": { "value": 50, "density": { "enable": true, "value_area": 800 } }, "color": { "value": "#D4A017" }, "shape": { "type": "line", "stroke": { "width": 2, "color": "#D4A017" } }, "opacity": { "value": 0.8, "random": true, "anim": { "enable": true, "speed": 1, "opacity_min": 0.5 } }, "size": { "value": 0 }, "line_linked": { "enable": true, "distance": 150, "color": "#D4A017", "opacity": 0.8, "width": 2 }, "move": { "enable": true, "speed": 2, "direction": "random", "random": true, "straight": false, "out_mode": "out", "bounce": false, "attract": { "enable": false } } },
      "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "repulse" }, "onclick": { "enable": false } } },
      "retina_detect": true
    });

    const tg = window.Telegram.WebApp;
    tg.ready();
    tg.expand();
    const chatId = tg.initDataUnsafe?.user?.id;
    if (chatId && !$_SESSION['chat_id']) {
        window.location.href = `?chat_id=${chatId}`;
    }
  </script>
</body>
</html>