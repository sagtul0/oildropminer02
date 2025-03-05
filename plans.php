<?php
include 'header.php'; // این فایل سشن را استارت می‌کند و استایل را لود می‌کند

// اگر کاربر لاگین نیست، پیام بده
if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger text-center mt-3'>Please <a href='login.php'>login</a> first.</div>";
    include 'footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];

// گرفتن اطلاعات کاربر از دیتابیس
$stmt = $conn->prepare("SELECT balance, boost_multiplier, auto_clicker, auto_clicker_expiration FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("User not found for ID: $user_id in plans.php");
    echo "<div class='alert alert-danger text-center mt-3'>User not found!</div>";
    include 'footer.php';
    exit();
}
$user = $result->fetch_assoc();
$balance = (float)$user['balance'];
$boost_multiplier = (float)$user['boost_multiplier'] ?? 1.0;
$auto_clicker = (bool)$user['auto_clicker'];
$auto_clicker_expiration = $user['auto_clicker_expiration'] ? new DateTime($user['auto_clicker_expiration']) : null;

// تعریف پلن‌ها و قیمت‌ها (به TON)
$plans = [
    "2x" => ["multiplier" => 2.0, "price" => 0.2, "name" => "2x Boost"],
    "5x" => ["multiplier" => 5.0, "price" => 0.5, "name" => "5x Boost"],
    "10x" => ["multiplier" => 10.0, "price" => 2.25, "name" => "10x Boost"],
    "auto_clicker" => ["price" => 5.0, "duration_days" => 30, "name" => "Auto Clicker (30 Days)"]
];

// پردازش خرید پلن (اگر فرم ارسال شده)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $plan = $_POST['plan'];

    if (!isset($plans[$plan])) {
        echo "<div class='alert alert-danger text-center mt-3'>Invalid plan!</div>";
    } else {
        $plan_info = $plans[$plan];
        $plan_price = $plan_info['price'];

        // بررسی موجودی
        if ($balance < $plan_price) {
            echo "<div class='alert alert-danger text-center mt-3'>
                    Insufficient funds. Your balance is " . number_format($balance, 2) . " TON. Please go to <a href='dashboard.php'>Dashboard</a> to recharge your balance.
                  </div>";
        } else {
            // کسر مبلغ از موجودی
            $new_balance = $balance - $plan_price;

            // به‌روزرسانی بوست یا اتو‌کلیکر
            if (isset($plan_info['multiplier'])) {
                // به‌روزرسانی ضریب بوست
                $new_multiplier = $plan_info['multiplier'];
                $update = $conn->prepare("UPDATE users SET balance = ?, boost_multiplier = ? WHERE id = ?");
                $update->bind_param("ddi", $new_balance, $new_multiplier, $user_id);
            } elseif ($plan === "auto_clicker") {
                // بررسی انقضای اتو‌کلیکر قبلی
                $now = new DateTime();
                if ($auto_clicker_expiration && $auto_clicker_expiration > $now && $auto_clicker) {
                    echo "<div class='alert alert-danger text-center mt-3'>
                            Auto Clicker is already active until " . $auto_clicker_expiration->format('Y-m-d H:i:s') . ".
                          </div>";
                    include 'footer.php';
                    exit();
                }
                
                // فعال‌سازی اتو‌کلیکر (برای 30 روز)
                $expiration_date = date('Y-m-d H:i:s', strtotime('+30 days'));
                $update = $conn->prepare("UPDATE users SET balance = ?, auto_clicker = 1, auto_clicker_expiration = ? WHERE id = ?");
                $update->bind_param("dsi", $new_balance, $expiration_date, $user_id);
            }

            if ($update->execute()) {
                // به‌روزرسانی اطلاعات کاربر در متغیرها
                $balance = $new_balance;
                if (isset($new_multiplier)) {
                    $boost_multiplier = $new_multiplier;
                }
                if ($plan === "auto_clicker") {
                    $auto_clicker = true;
                    $auto_clicker_expiration = new DateTime($expiration_date);
                }

                // پیام موفقیت
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
    include 'footer.php';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Plans - Oil Drop Miner</title>
  <!-- لینک‌های CSS و Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .plans-text {
      color: #ffffff !important; /* سفید برای تضمین خوانایی */
      font-weight: bold !important;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8) !important; /* سایه قوی‌تر برای کنتراست بهتر */
      font-size: 1.2rem !important; /* اندازه متن برای خوانایی */
    }

    .alert {
      color: #ffffff !important; /* سفید برای خوانایی روی پس‌زمینه تیره */
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; /* سایه برای کنتراست بهتر */
    }

    .alert-success {
      background-color: #28a745 !important; /* سبز تیره‌تر برای هماهنگی */
      border-color: #1e7e34 !important;
    }

    .alert-danger {
      background-color: #dc3545 !important; /* قرمز تیره‌تر برای هماهنگی */
      border-color: #bd2130 !important;
    }

    .btn-warning, .btn-success {
      color: #ffffff !important; /* سفید برای متن دکمه‌ها */
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important;
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

    /* تنظیم کانتینر اصلی */
    .container {
      max-width: 1000px; /* محدود کردن عرض صفحه */
      margin-top: 20px !important; /* کاهش مارجین بالا */
      padding-top: 0 !important; /* حذف پدینگ اضافی بالا */
    }

    /* حذف نواحی خاکستری غیرضروری */
    body::before {
      background: rgba(0, 0, 0, 0.5) !important; /* لایه نیمه‌شفاف برای هماهنگی */
    }

    #particles-js {
      z-index: 0 !important; /* اطمینان از اینکه خطوط طلایی زیر محتوا باشن */
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

  <div class="container mt-5">
    <h2 class="mb-4 text-center text-warning">Upgrade Your Plan</h2>
    <p class="plans-text text-center">
        Activate a plan to boost your mining or enable auto-clicker. Your current balance: <span class="fw-bold"><?php echo number_format($balance, 2); ?> TON</span>
    </p>

    <div class="row justify-content-center">
        <div class="col-md-3 mb-3">
            <form method="post">
                <button type="submit" name="plan" value="2x" class="btn btn-warning w-100 fw-bold"><?php echo $plans['2x']['name']; ?> (<?php echo $plans['2x']['price']; ?> TON)</button>
            </form>
        </div>
        <div class="col-md-3 mb-3">
            <form method="post">
                <button type="submit" name="plan" value="5x" class="btn btn-warning w-100 fw-bold"><?php echo $plans['5x']['name']; ?> (<?php echo $plans['5x']['price']; ?> TON)</button>
            </form>
        </div>
        <div class="col-md-3 mb-3">
            <form method="post">
                <button type="submit" name="plan" value="10x" class="btn btn-warning w-100 fw-bold"><?php echo $plans['10x']['name']; ?> (<?php echo $plans['10x']['price']; ?> TON)</button>
            </form>
        </div>
        <div class="col-md-3 mb-3">
            <form method="post">
                <button type="submit" name="plan" value="auto_clicker" class="btn btn-success w-100 fw-bold"><?php echo $plans['auto_clicker']['name']; ?> (<?php echo $plans['auto_clicker']['price']; ?> TON)</button>
            </form>
        </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <!-- لینک JS Bootstrap و افکت خطوط -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
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