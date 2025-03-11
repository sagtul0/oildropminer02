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

$stmt = $conn->prepare("SELECT balance, boost_multiplier, auto_clicker, auto_clicker_expiration FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("User not found for ID/Chat_ID: $user_id in plans.php");
    echo "<div class='alert alert-danger text-center mt-3'>User not found!</div>";
    include 'footer.php';
    exit();
}
$user = $result->fetch_assoc();
$balance = (float)$user['balance'];
$boost_multiplier = (float)($user['boost_multiplier'] ?? 1.0);
$auto_clicker = (bool)$user['auto_clicker'];
$auto_clicker_expiration = $user['auto_clicker_expiration'] ? new DateTime($user['auto_clicker_expiration']) : null;

$plans = [
    "2x" => ["multiplier" => 2.0, "price" => 0.2, "name" => "2x Boost"],
    "5x" => ["multiplier" => 5.0, "price" => 0.5, "name" => "5x Boost"],
    "10x" => ["multiplier" => 10.0, "price" => 2.25, "name" => "10x Boost"],
    "auto_clicker" => ["price" => 5.0, "duration_days" => 30, "name" => "Auto Clicker (30 Days)"]
];

// پردازش خرید پلن (فقط در Web App)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (!isset($_SESSION['chat_id'])) {
        echo "<div class='alert alert-warning text-center mt-3'>Purchases can only be done via Telegram Web App!</div>";
        include 'footer.php';
        exit();
    }

    $plan = filter_var($_POST['plan'], FILTER_SANITIZE_STRING);
    if (!isset($plans[$plan])) {
        echo "<div class='alert alert-danger text-center mt-3'>Invalid plan!</div>";
    } else {
        $plan_info = $plans[$plan];
        $plan_price = $plan_info['price'];

        if ($balance < $plan_price) {
            echo "<div class='alert alert-danger text-center mt-3'>
                    Insufficient funds. Your balance is " . number_format($balance, 2) . " TON. Please go to <a href='dashboard.php'>Dashboard</a> to recharge your balance.
                  </div>";
        } else {
            $new_balance = $balance - $plan_price;

            if (isset($plan_info['multiplier'])) {
                $new_multiplier = $plan_info['multiplier'];
                $update = $conn->prepare("UPDATE users SET balance = ?, boost_multiplier = ? WHERE id = ? OR chat_id = ?");
                $update->bind_param("ddii", $new_balance, $new_multiplier, $user_id, $user_id);
            } elseif ($plan === "auto_clicker") {
                $now = new DateTime();
                if ($auto_clicker_expiration && $auto_clicker_expiration > $now && $auto_clicker) {
                    echo "<div class='alert alert-danger text-center mt-3'>
                            Auto Clicker is already active until " . $auto_clicker_expiration->format('Y-m-d H:i:s') . ".
                          </div>";
                    include 'footer.php';
                    exit();
                }

                $expiration_date = date('Y-m-d H:i:s', strtotime('+30 days'));
                $update = $conn->prepare("UPDATE users SET balance = ?, auto_clicker = 1, auto_clicker_expiration = ? WHERE id = ? OR chat_id = ?");
                $update->bind_param("dsii", $new_balance, $expiration_date, $user_id, $user_id);
            }

            if ($update->execute()) {
                $balance = $new_balance;
                if (isset($new_multiplier)) {
                    $boost_multiplier = $new_multiplier;
                }
                if ($plan === "auto_clicker") {
                    $auto_clicker = true;
                    $auto_clicker_expiration = new DateTime($expiration_date);
                }

                echo "<div class='alert alert-success text-center mt-3'>
                        " . $plan_info['name'] . " purchased successfully! New balance: " . number_format($balance, 2) . " TON.
                      </div>";
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>
                        Error purchasing plan: " . $update->error . "
                      </div>";
            }
            $update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Plans - Oil Drop Miner</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .plans-text { color: #ffffff !important; font-weight: bold !important; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8) !important; font-size: 1.2rem !important; }
    .alert { color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; }
    .alert-success { background-color: #28a745 !important; border-color: #1e7e34 !important; }
    .alert-danger { background-color: #dc3545 !important; border-color: #bd2130 !important; }
    .btn-warning, .btn-success { color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; }
    .container { max-width: 1000px; margin-top: 20px !important; padding-top: 0 !important; }
    body::before { background: rgba(0, 0, 0, 0.5) !important; }
    #particles-js { z-index: 0 !important; }
  </style>
</head>
<body>
  <div id="particles-js"></div>

  <div class="container mt-5">
    <h2 class="mb-4 text-center text-warning">Upgrade Your Plan</h2>
    <p class="plans-text text-center">
        Activate a plan to boost your mining or enable auto-clicker. Your current balance: <span class="fw-bold"><?php echo number_format($balance, 2); ?> TON</span>
    </p>

    <div class="row justify-content-center">
        <div class="col-md-3 mb-3">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="plan" value="2x" class="btn btn-warning w-100 fw-bold" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>><?php echo $plans['2x']['name']; ?> (<?php echo $plans['2x']['price']; ?> TON)</button>
            </form>
        </div>
        <div class="col-md-3 mb-3">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="plan" value="5x" class="btn btn-warning w-100 fw-bold" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>><?php echo $plans['5x']['name']; ?> (<?php echo $plans['5x']['price']; ?> TON)</button>
            </form>
        </div>
        <div class="col-md-3 mb-3">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="plan" value="10x" class="btn btn-warning w-100 fw-bold" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>><?php echo $plans['10x']['name']; ?> (<?php echo $plans['10x']['price']; ?> TON)</button>
            </form>
        </div>
        <div class="col-md-3 mb-3">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="plan" value="auto_clicker" class="btn btn-success w-100 fw-bold" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>><?php echo $plans['auto_clicker']['name']; ?> (<?php echo $plans['auto_clicker']['price']; ?> TON)</button>
            </form>
        </div>
    </div>
    <?php if (!isset($_SESSION['chat_id'])): ?>
        <p class="text-center text-warning mt-3">Purchases can only be done via Telegram Web App!</p>
    <?php endif; ?>
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