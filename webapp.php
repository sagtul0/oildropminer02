<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=utf-8');

include 'config.php'; // لود کردن فایل تنظیمات

// شروع سشن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تولید CSRF token برای امنیت فرم‌ها
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// دیباگ درخواست
error_log("Request Headers: " . print_r($_SERVER, true));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Query String: " . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : 'Not set'));

// چک کردن وجود chat_id توی سشن
if (!isset($_SESSION['chat_id'])) {
    error_log("No chat_id in session yet. Waiting for client-side initData.");
} else {
    $chat_id = $_SESSION['chat_id'];
    if (!isset($conn) || $conn === null) {
        error_log("Error: PDO connection not established. Check config.php.");
        die("Error: Database connection not established. Please check server logs.");
    }

    try {
        // گرفتن اطلاعات کاربر از دیتابیس
        $stmt = $conn->prepare("SELECT oil_drops, balance, invite_reward, today_clicks, boost_multiplier, auto_clicker, auto_clicker_expiration FROM users WHERE chat_id = :chat_id");
        $stmt->execute(['chat_id' => $chat_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            die("User not found. Please start the bot with /start. Chat ID: " . htmlspecialchars($chat_id));
        }

        // مقداردهی متغیرها
        $oil_drops = (int)$user['oil_drops'];
        $balance = (float)$user['balance'];
        $referrals = (int)$user['invite_reward'];
        $today_clicks = (int)$user['today_clicks'];
        $boost_multiplier = (float)($user['boost_multiplier'] ?? 1.0);
        $auto_clicker = (bool)$user['auto_clicker'];
        $auto_clicker_expiration = $user['auto_clicker_expiration'] ? new DateTime($user['auto_clicker_expiration']) : null;

        $clicks_left = max(0, 1000 - $today_clicks);

        // گرفتن کارت‌های فعال کاربر
        $active_cards_stmt = $conn->prepare("SELECT card_id, card_type, reward FROM user_cards WHERE chat_id = :chat_id");
        $active_cards_stmt->execute(['chat_id' => $chat_id]);
        $active_cards = $active_cards_stmt->fetchAll(PDO::FETCH_ASSOC);

        // گرفتن پلن‌های خریداری‌شده
        $plans_stmt = $conn->prepare("SELECT plan_type, purchase_date FROM user_plans WHERE chat_id = :chat_id AND is_active = TRUE");
        $plans_stmt->execute(['chat_id' => $chat_id]);
        $active_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        die("Database error: " . htmlspecialchars($e->getMessage()));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oil Drop Miner Web App</title>
    <link rel="preload" href="assets/images/backgrounds/auth_background_simple.jpg" as="image">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="background-container">
        <img src="assets/images/backgrounds/auth_background_simple.jpg" alt="Background">
    </div>

    <!-- منوی ناوبری -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Oil Drop Miner</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="webapp.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mine.php">Mine</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="plans.php">Plans</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="deposit.php">Deposit</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="referrals.php">Referrals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="boosts.php">Boosts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cards.php">Cards</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="withdraw.php">Withdraw</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['chat_id'])): ?>
            <h1 class="text-center text-warning mb-4">Oil Drop Miner Dashboard</h1>

            <!-- نمایش آمار کاربر -->
            <div class="dashboard-card">
                <h5>Your Stats</h5>
                <p class="card-text">Oil Drops: <strong id="oil-count"><?php echo htmlspecialchars($oil_drops); ?></strong></p>
                <p class="card-text">TON Balance: <strong class="balance-text"><?php echo number_format($balance, 2); ?> TON</strong></p>
                <p class="card-text">Referrals: <strong><?php echo htmlspecialchars($referrals); ?></strong></p>
                <p class="card-text">Current Boost: <strong class="fw-bold"><?php echo htmlspecialchars($boost_multiplier); ?>×</strong></p>
                <p class="card-text">Auto Clicker: <strong><?php echo $auto_clicker ? 'Active (Until ' . $auto_clicker_expiration->format('Y-m-d H:i:s') . ')' : 'Not Active'; ?></strong></p>
                <p class="card-text">Today's Clicks: <strong><?php echo htmlspecialchars($today_clicks); ?>/1000</strong></p>
                <p class="card-text" id="clicks-left">Clicks Left: <strong><?php echo htmlspecialchars($clicks_left); ?></strong></p>
            </div>

            <!-- دکمه Mine Oil -->
            <div class="dashboard-card text-center">
                <button id="mine-btn" class="btn btn-warning oil-btn shine" <?php echo $clicks_left <= 0 ? 'disabled' : ''; ?>>Mine Oil ⛏️</button>
            </div>

            <!-- نمایش کارت‌های فعال -->
            <h2 class="text-center text-warning mb-4">Active Cards</h2>
            <?php if (count($active_cards) > 0): ?>
                <?php foreach ($active_cards as $card): ?>
                    <div class="dashboard-card">
                        <h5>Card ID: <?php echo htmlspecialchars($card['card_id']); ?> (Type: <?php echo htmlspecialchars($card['card_type']); ?>)</h5>
                        <p class="card-text">Reward: <strong><?php echo htmlspecialchars($card['reward']); ?> Oil Drops / 8h</strong></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-white">You have no active cards.</p>
            <?php endif; ?>

            <!-- نمایش پلن‌های فعال -->
            <h2 class="text-center text-warning mb-4">Active Plans</h2>
            <?php if (count($active_plans) > 0): ?>
                <?php foreach ($active_plans as $plan): ?>
                    <div class="dashboard-card">
                        <h5>Plan: <?php echo htmlspecialchars($plan['plan_type']); ?></h5>
                        <p class="card-text">Purchased On: <strong><?php echo htmlspecialchars($plan['purchase_date']); ?></strong></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-white">You have no active plans.</p>
            <?php endif; ?>

            <!-- فرم دیپازیت TON -->
            <h2 class="text-center text-warning mb-4">Deposit TON</h2>
            <div class="dashboard-card">
                <form action="deposit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <input type="number" id="amount" name="amount" class="oil-input" placeholder="Enter TON amount" step="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-warning oil-btn">Deposit</button>
                </form>
                <p class="text-center text-white mt-2">Send TON to: <strong><?php echo htmlspecialchars('UQDCy7GZFzZCUwM4_R7ZgqZW34aDfgV9CEY8BX-ucyQRxGfo'); ?></strong></p>
            </div>
        <?php else: ?>
            <p class="text-center text-warning">Loading... Please wait while we authenticate you via Telegram.</p>
        <?php endif; ?>
    </div>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>