<?php
include 'header.php'; // سشن از اینجا شروع می‌شود

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger text-center mt-3'>Please <a href='login.php'>login</a> first.</div>";
    include 'footer.php';
    exit();
}

// گرفتن اطلاعات کاربر از دیتابیس
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT oil_drops, today_clicks, last_click_day, boost_multiplier, auto_clicker, ton_wallet_address, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("User not found for ID: $user_id in dashboard.php");
    echo "<div class='alert alert-danger text-center mt-3'>User not found!</div>";
    include 'footer.php';
    exit();
}
$user = $result->fetch_assoc();
$oil_drops = (int)$user['oil_drops'];
$today_clicks = (int)$user['today_clicks'];
$last_click_day = $user['last_click_day'];
$boost_multiplier = (float)$user['boost_multiplier'] ?? 1.0;
$auto_clicker = (bool)$user['auto_clicker'];
$ton_wallet_address = $user['ton_wallet_address'];
$balance = (float)$user['balance'];

// ایجاد توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// پردازش فرم وصل کردن کیف پول
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['connect_wallet'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<div class='alert alert-danger text-center mt-3'>CSRF token mismatch. Please try again.</div>";
    } else {
        $wallet_address = filter_var($_POST['wallet_address'], FILTER_SANITIZE_STRING);
        if ($wallet_address) {
            // اینجا باید با ربات تلگرام یا API TON ارتباط برقرار کنی تا کاربر رو به کیف پول هدایت کنی
            // فعلاً فقط به‌روزرسانی دیتابیس رو شبیه‌سازی می‌کنم
            $update = $conn->prepare("UPDATE users SET ton_wallet_address = ? WHERE id = ?");
            $update->bind_param("si", $wallet_address, $user_id);
            if ($update->execute()) {
                echo "<div class='alert alert-success text-center mt-3'>Wallet successfully connected!</div>";
                $ton_wallet_address = $wallet_address;
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>Error connecting wallet: " . $update->error . "</div>";
            }
            $update->close();
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Invalid wallet address.</div>";
        }
    }
    include 'footer.php';
    exit();
}

// پردازش فرم واریز TON
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['deposit'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<div class='alert alert-danger text-center mt-3'>CSRF token mismatch. Please try again.</div>";
    } elseif (!$ton_wallet_address) {
        echo "<div class='alert alert-danger text-center mt-3'>Please connect your TON wallet first.</div>";
    } else {
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        if ($amount > 0) {
            // اینجا باید کاربر رو به صفحه پرداخت (مثلاً ربات تلگرام یا اپلیکیشن کیف پول) هدایت کنی
            // فعلاً فقط به‌روزرسانی بالانس رو شبیه‌سازی می‌کنم
            $new_balance = $balance + $amount;
            $update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $update->bind_param("di", $new_balance, $user_id);
            if ($update->execute()) {
                echo "<div class='alert alert-success text-center mt-3'>Deposited $amount TON successfully! New balance: $new_balance TON</div>";
                $balance = $new_balance;
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>Error depositing TON: " . $update->error . "</div>";
            }
            $update->close();
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Invalid deposit amount.</div>";
        }
    }
    include 'footer.php';
    exit();
}

// پردازش تغییر آدرس کیف پول
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_wallet'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<div class='alert alert-danger text-center mt-3'>CSRF token mismatch. Please try again.</div>";
    } else {
        $new_wallet_address = filter_var($_POST['new_wallet_address'], FILTER_SANITIZE_STRING);
        if ($new_wallet_address) {
            // اینجا باید با ربات تلگرام یا API TON ارتباط برقرار کنی تا تغییر رو تأیید کنی
            // فعلاً فقط به‌روزرسانی دیتابیس رو شبیه‌سازی می‌کنم
            $update = $conn->prepare("UPDATE users SET ton_wallet_address = ? WHERE id = ?");
            $update->bind_param("si", $new_wallet_address, $user_id);
            if ($update->execute()) {
                echo "<div class='alert alert-success text-center mt-3'>Wallet address changed successfully!</div>";
                $ton_wallet_address = $new_wallet_address;
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>Error changing wallet address: " . $update->error . "</div>";
            }
            $update->close();
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Invalid wallet address.</div>";
        }
    }
    include 'footer.php';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Oil Drop Miner</title>
  <!-- لینک‌های CSS و Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .dashboard-text {
      color: #ffffff !important; /* سفید برای تضمین خوانایی */
      font-weight: bold !important;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8) !important; /* سایه قوی‌تر برای کنتراست بهتر */
      font-size: 1.2rem !important; /* اندازه متن برای خوانایی */
    }

    .alert {
      color: #000000 !important; /* مشکی برای پیام‌های هشدار روی پس‌زمینه روشن */
      text-shadow: none !important;
    }

    .alert-success {
      background-color: #d4edda !important; /* سبز روشن‌تر برای کنتراست بهتر */
      border-color: #c3e6cb !important;
    }

    .alert-danger {
      background-color: #f8d7da !important; /* صورتی روشن‌تر برای کنتراست بهتر */
      border-color: #f5c6cb !important;
    }

    .wallet-form {
      margin-top: 10px; /* فاصله کمتر */
      max-width: 300px; /* اندازه کوچیک‌تر برای فرم کیف پول */
      margin-left: auto;
      margin-right: auto;
    }

    .wallet-input {
      background-color: #333 !important;
      border: 1px solid #b8860b !important;
      color: #ffffff !important;
      transition: all 0.3s ease;
      font-size: 0.9rem !important; /* فونت کوچیک‌تر */
    }

    .wallet-input:focus {
      border-color: #daa520 !important;
      box-shadow: 0 0 8px rgba(218, 165, 32, 0.5) !important;
      background-color: #444 !important;
    }

    .btn-wallet {
      background-color: #b8860b !important;
      border-color: #b8860b !important;
      color: #ffffff !important;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important;
      font-size: 0.9rem !important; /* فونت کوچیک‌تر */
      padding: 6px 12px !important; /* اندازه کوچیک‌تر دکمه */
    }

    .btn-wallet:hover {
      background-color: #daa520 !important;
      transform: scale(1.05) !important;
    }

    .container {
      max-width: 1000px; /* محدود کردن عرض صفحه */
      margin-top: 20px !important; /* کاهش مارجین بالا */
      padding-top: 0 !important; /* حذف پدینگ اضافی بالا */
    }

    .text-success, .text-danger {
      color: #ffffff !important; /* سفید برای متون موفقیت و خطا */
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important;
    }

    .fw-bold {
      color: #ffcc00 !important; /* طلایی روشن برای مقادیر مشخص مثل Oil Drops */
    }

    /* تنظیم ناوبر */
    .navbar {
      height: 60px !important; /* کاهش ارتفاع ناوبر */
      background-color: #1a1a1a !important; /* رنگ تیره‌تر برای هماهنگی */
    }

    .navbar-brand img {
      width: 50px !important; /* اندازه کوچیک‌تر لوگو */
      height: auto;
      margin-right: 5px;
    }

    .navbar-brand {
      font-size: 1.1rem !important; /* فونت کوچیک‌تر برای ناوبر */
      color: #ffcc00 !important;
      font-weight: bold;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7);
    }

    .nav-link {
      font-size: 1rem !important; /* فونت کوچیک‌تر برای لینک‌ها */
      color: #ffffff !important; /* سفید برای خوانایی */
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }

    .nav-link:hover {
      color: #ffcc00 !important; /* طلایی روشن در هورور */
    }

    /* تنظیم فوتر */
    footer {
      position: relative !important;
      bottom: 0 !important;
      width: 100% !important;
      background-color: #1a1a1a !important; /* رنگ تیره‌تر برای فوتر */
      padding: 10px 0 !important; /* پدینگ کوچیک‌تر */
      margin-top: 20px !important; /* فاصله از محتوا */
    }

    footer p {
      color: #ffffff !important; /* سفید برای متن فوتر */
      font-size: 0.9rem !important; /* فونت کوچیک‌تر */
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }

    footer a {
      color: #ffcc00 !important; /* طلایی روشن برای لینک‌ها */
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }
  </style>
</head>
<body>
  <!-- کانتینر برای افکت خطوط -->
  <div id="particles-js"></div>

  <div class="container mt-5 text-center">
    <h2 class="mb-3"><span class="welcome-text">Welcome to</span> <span style="color: #ffcc00;">Oil Drop Miner Dashboard</span></h2>
    <p><span class="dashboard-text warning">Your Oil Drops:</span> <span id="oil-count" class="fw-bold"><?php echo $oil_drops; ?></span></p>
    <p><span class="dashboard-text warning">Current Boost:</span> <span class="fw-bold"><?php echo number_format($boost_multiplier, 1); ?>×</span></p>
    <p><span class="dashboard-text">Auto Clicker:</span> <span class="text-<?php echo $auto_clicker ? 'success' : 'danger'; ?> fw-bold"><?php echo $auto_clicker ? 'Active ✅' : 'Inactive ❌'; ?></span></p>
    <p><span class="dashboard-text warning">Clicks Left Today:</span> <span id="clicks-left" class="fw-bold"><?php echo 1000 - $today_clicks; ?></span></p>
    <p><span class="dashboard-text warning">TON Balance:</span> <span class="fw-bold balance-text"><?php echo number_format($balance, 2); ?> TON</span></p>

    <!-- بخش وصل کردن کیف پول TON -->
    <div class="wallet-form">
        <?php if (empty($ton_wallet_address)): ?>
            <form method="post">
                <input type="text" class="wallet-input mb-2" name="wallet_address" placeholder="Enter TON Wallet Address" required>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="connect_wallet" class="btn btn-wallet">Connect TON Wallet</button>
            </form>
        <?php else: ?>
            <p class="text-white">Connected Wallet: <span class="fw-bold"><?php echo $ton_wallet_address; ?></span></p>
            <form method="post" class="mt-2">
                <input type="text" class="wallet-input mb-2" name="new_wallet_address" placeholder="New TON Wallet Address" required>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="change_wallet" class="btn btn-wallet">Change Wallet</button>
            </form>
            <!-- فرم واریز TON -->
            <form method="post" class="mt-3">
                <input type="number" class="wallet-input mb-2" name="amount" placeholder="Enter TON Amount" step="0.1" required>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="deposit" class="btn btn-wallet">Deposit</button>
            </form>
        <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <!-- لینک JS Bootstrap و افکت خطوط -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    // افکت خطوط طلایی براق
    particlesJS("particles-js", {
      "particles": {
        "number": { "value": 50, "density": { "enable": true, "value_area": 800 } },
        "color": { "value": "#D4A017" }, // طلایی غنی برای حس ثروت
        "shape": { "type": "line", "stroke": { "width": 2, "color": "#D4A017" } },
        "opacity": { 
          "value": 0.8, 
          "random": true,
          "anim": { "enable": true, "speed": 1, "opacity_min": 0.5 }
        },
        "size": { "value": 0 }, // اندازه ذرات صفر می‌ذاریم چون خطوط داریم
        "line_linked": { 
          "enable": true, 
          "distance": 150, 
          "color": "#D4A017", 
          "opacity": 0.8, 
          "width": 2 
        },
        "move": {
          "enable": true,
          "speed": 2, // سرعت ملایم برای حس لوکسی
          "direction": "random", // حرکت تصادفی برای خطوط براق
          "random": true,
          "straight": false,
          "out_mode": "out",
          "bounce": false,
          "attract": { "enable": false }
        }
      },
      "interactivity": {
        "detect_on": "canvas",
        "events": { "onhover": { "enable": true, "mode": "repulse" }, "onclick": { "enable": false } }
      },
      "retina_detect": true
    });
  </script>
</body>
</html>