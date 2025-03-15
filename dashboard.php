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
$result_block = $stmt->get_result();
$user_blocked = $result_block->fetch_assoc();
if ($user_blocked['is_blocked']) {
    echo "<div class='alert alert-danger text-center mt-3'>Your account has been blocked.</div>";
    include 'footer.php';
    exit;
}
$stmt->close();

// گرفتن اطلاعات کاربر
$stmt = $conn->prepare("SELECT oil_drops, today_clicks, boost_multiplier, auto_clicker, ton_wallet_address, balance FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("User not found for ID/Chat_ID: $user_id in dashboard.php");
    echo "<div class='alert alert-danger text-center mt-3'>User not found!</div>";
    include 'footer.php';
    exit();
}
$user = $result->fetch_assoc();
$oil_drops = (int)$user['oil_drops'];
$today_clicks = (int)$user['today_clicks'];
$boost_multiplier = (float)($user['boost_multiplier'] ?? 1.0);
$auto_clicker = (bool)$user['auto_clicker'];
$ton_wallet_address = $user['ton_wallet_address'];
$balance = (float)$user['balance'];

// گرفتن اطلاعات Oil Cards
$stmt = $conn->prepare("SELECT card_name, card_type, is_active, activation_date FROM oil_cards WHERE user_id = ? AND is_active = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cards_result = $stmt->get_result();
$active_cards = [];
while ($card = $cards_result->fetch_assoc()) {
    $active_cards[] = $card;
}

// گرفتن پلن‌های خریداری‌شده
$stmt = $conn->prepare("SELECT plan_type, purchase_date FROM user_plans WHERE user_id = ? AND is_active = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$plans_result = $stmt->get_result();
$active_plans = [];
while ($plan = $plans_result->fetch_assoc()) {
    $active_plans[] = $plan;
}

// پردازش فرم وصل کردن کیف پول (فقط در Web App)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['connect_wallet']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (!isset($_SESSION['chat_id'])) {
        echo "<div class='alert alert-warning text-center mt-3'>Wallet operations can only be done via Telegram Web App!</div>";
        include 'footer.php';
        exit();
    }

    $wallet_address = filter_var($_POST['wallet_address'], FILTER_SANITIZE_STRING);
    if (!preg_match('/^EQ[a-zA-Z0-9]{47}$/', $wallet_address)) {
        echo "<div class='alert alert-danger text-center mt-3'>Invalid TON wallet address format.</div>";
    } else {
        $update = $conn->prepare("UPDATE users SET ton_wallet_address = ? WHERE id = ? OR chat_id = ?");
        $update->bind_param("sii", $wallet_address, $user_id, $user_id);
        if ($update->execute()) {
            echo "<div class='alert alert-success text-center mt-3'>Wallet successfully connected!</div>";
            $ton_wallet_address = $wallet_address;
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Error connecting wallet: " . $update->error . "</div>";
        }
        $update->close();
    }
    include 'footer.php';
    exit();
}

// پردازش فرم واریز TON (فقط در Web App)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['deposit']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (!isset($_SESSION['chat_id'])) {
        echo "<div class='alert alert-warning text-center mt-3'>Deposits can only be done via Telegram Web App!</div>";
        include 'footer.php';
        exit();
    }

    if (!$ton_wallet_address) {
        echo "<div class='alert alert-danger text-center mt-3'>Please connect your TON wallet first.</div>";
    } else {
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        if ($amount <= 0) {
            echo "<div class='alert alert-danger text-center mt-3'>Invalid deposit amount.</div>";
        } else {
            $new_balance = $balance + $amount;
            $update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ? OR chat_id = ?");
            $update->bind_param("dii", $new_balance, $user_id, $user_id);
            if ($update->execute()) {
                echo "<div class='alert alert-success text-center mt-3'>Deposited $amount TON successfully! New balance: $new_balance TON</div>";
                $balance = $new_balance;
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>Error depositing TON: " . $update->error . "</div>";
            }
            $update->close();
        }
    }
    include 'footer.php';
    exit();
}

// پردازش تغییر آدرس کیف پول (فقط در Web App)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_wallet']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (!isset($_SESSION['chat_id'])) {
        echo "<div class='alert alert-warning text-center mt-3'>Wallet operations can only be done via Telegram Web App!</div>";
        include 'footer.php';
        exit();
    }

    $new_wallet_address = filter_var($_POST['new_wallet_address'], FILTER_SANITIZE_STRING);
    if (!preg_match('/^EQ[a-zA-Z0-9]{47}$/', $new_wallet_address)) {
        echo "<div class='alert alert-danger text-center mt-3'>Invalid TON wallet address format.</div>";
    } else {
        $update = $conn->prepare("UPDATE users SET ton_wallet_address = ? WHERE id = ? OR chat_id = ?");
        $update->bind_param("sii", $new_wallet_address, $user_id, $user_id);
        if ($update->execute()) {
            echo "<div class='alert alert-success text-center mt-3'>Wallet address changed successfully!</div>";
            $ton_wallet_address = $new_wallet_address;
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Error changing wallet address: " . $update->error . "</div>";
        }
        $update->close();
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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- اضافه کردن متا تگ CSRF برای استفاده در main.js -->
  <meta name="csrf_token" content="<?php echo $_SESSION['csrf_token']; ?>">
  <style>
    .dashboard-text { color: #ffffff !important; font-weight: bold !important; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8) !important; font-size: 1.2rem !important; }
    .dashboard-text.warning { color: #ffcc00 !important; }
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
    .card-table { margin-top: 20px; background-color: rgba(255, 255, 255, 0.1); border-radius: 10px; padding: 15px; }
    .card-table th, .card-table td { color: #ffffff; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7); }
  </style>
</head>
<body>
  <div id="particles-js"></div>

  <div class="container mt-5 text-center">
    <h2 class="mb-3"><span class="welcome-text">Welcome to</span> <span style="color: #ffcc00;">Oil Drop Miner Dashboard</span></h2>
    <p><span class="dashboard-text warning">Your Oil Drops:</span> <span id="oil-count" class="fw-bold"><?php echo $oil_drops; ?></span></p>
    <p><span class="dashboard-text warning">Current Boost:</span> <span class="fw-bold"><?php echo number_format($boost_multiplier, 1); ?>×</span></p>
    <p><span class="dashboard-text">Auto Clicker:</span> <span class="text-<?php echo $auto_clicker ? 'success' : 'danger'; ?> fw-bold"><?php echo $auto_clicker ? 'Active ✅' : 'Inactive ❌'; ?></span></p>
    <p><span class="dashboard-text warning">Today's Clicks:</span> <span id="clicks-today" class="fw-bold"><?php echo $today_clicks; ?>/1000</span></p>
    <p><span class="dashboard-text warning">TON Balance:</span> <span class="fw-bold balance-text"><?php echo number_format($balance, 2); ?> TON</span></p>

    <!-- نمایش کارت‌های فعال -->
    <?php if (!empty($active_cards)): ?>
        <div class="card-table">
            <h4 class="text-warning mb-3">Active Oil Cards</h4>
            <table class="table table-dark">
                <thead>
                    <tr>
                        <th>Card Name</th>
                        <th>Type</th>
                        <th>Activation Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_cards as $card): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($card['card_name']); ?></td>
                            <td><?php echo htmlspecialchars($card['card_type']); ?></td>
                            <td><?php echo htmlspecialchars($card['activation_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="dashboard-text warning mt-3">No active Oil Cards found.</p>
    <?php endif; ?>

    <!-- نمایش پلن‌های فعال -->
    <?php if (!empty($active_plans)): ?>
        <div class="card-table">
            <h4 class="text-warning mb-3">Active Plans</h4>
            <table class="table table-dark">
                <thead>
                    <tr>
                        <th>Plan Type</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_plans as $plan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($plan['plan_type']); ?></td>
                            <td><?php echo htmlspecialchars($plan['purchase_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="dashboard-text warning mt-3">No active plans found.</p>
    <?php endif; ?>

    <!-- بخش وصل کردن کیف پول TON -->
    <div class="wallet-form">
        <?php if (empty($ton_wallet_address)): ?>
            <form method="post">
                <input type="text" class="wallet-input mb-2" name="wallet_address" placeholder="Enter TON Wallet Address" required>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="connect_wallet" class="btn btn-wallet" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>>Connect TON Wallet</button>
            </form>
            <?php if (!isset($_SESSION['chat_id'])): ?>
                <p class="text-warning mt-2">Wallet operations can only be done via Telegram Web App!</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-white">Connected Wallet: <span class="fw-bold"><?php echo htmlspecialchars($ton_wallet_address); ?></span></p>
            <form method="post" class="mt-2">
                <input type="text" class="wallet-input mb-2" name="new_wallet_address" placeholder="New TON Wallet Address" required>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="change_wallet" class="btn btn-wallet" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>>Change Wallet</button>
            </form>
            <!-- فرم واریز TON -->
            <form method="post" class="mt-3">
                <input type="number" class="wallet-input mb-2" name="amount" placeholder="Enter TON Amount" step="0.1" required>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="deposit" class="btn btn-wallet" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>>Deposit</button>
            </form>
            <?php if (!isset($_SESSION['chat_id'])): ?>
                <p class="text-warning mt-2">Wallet operations can only be done via Telegram Web App!</p>
            <?php endif; ?>
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