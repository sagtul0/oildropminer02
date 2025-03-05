<?php
include 'header.php'; // در این فایل سشن شروع می‌شود و استایل‌ها لود می‌شوند
include 'config/social_api.php'; // فایل API‌های شبکه‌های اجتماعی

// اگر کاربر لاگین نیست، پیام بده و خارج شو
if (!isset($_SESSION["user_id"])) {
    echo "<div class='alert alert-danger text-center mt-3'>Please <a href='login.php'>login</a> first.</div>";
    include 'footer.php';
    exit();
}

$user_id = $_SESSION["user_id"];

// گرفتن اطلاعات کاربر از جدول users (برای نمایش مقدار فعلی oil_drops)
$stmt = $conn->prepare("SELECT oil_drops FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("User not found for ID: $user_id in earn.php");
    echo "<div class='alert alert-danger text-center mt-3'>User not found.</div>";
    include 'footer.php';
    exit();
}
$user = $result->fetch_assoc();
$current_oil = (int)$user["oil_drops"];

// ایجاد توکن CSRF در ابتدای فایل
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // ایجاد توکن تصادفی
}

// اگر فرم ارسال شده است (متد POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // بررسی CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch for user ID: $user_id");
        echo "<div class='alert alert-danger text-center mt-3'>CSRF token mismatch. Please try again.</div>";
        include 'footer.php';
        exit();
    }

    // تعریف چند تسک: (کلید: name در فرم، مقدار: [نام تسک در DB, میزان پاداش, تابع بررسی])
    $tasks = [
        "join_telegram"     => ["telegram", 10, 'checkTelegramMembership'],
        "follow_x"          => ["x", 10, 'checkXFollow'],
        "subscribe_youtube" => ["youtube", 10, 'checkYouTubeSubscription'],
        "follow_instagram"  => ["instagram", 10, 'checkInstagramFollow']
    ];

    // بررسی می‌کنیم کدام کلید POST ست شده است.
    $task_completed = false;
    foreach ($tasks as $postKey => $taskData) {
        if (isset($_POST[$postKey])) {
            $taskName = $taskData[0];
            $reward   = $taskData[1];
            $checkFunction = $taskData[2];

            // بررسی خودکار با API
            if (function_exists($checkFunction)) {
                if ($checkFunction($user_id)) {
                    // ابتدا چک کنیم آیا کاربر این تسک را قبلاً انجام داده؟
                    $check = $conn->prepare("SELECT id FROM task_completions WHERE user_id = ? AND task_name = ?");
                    $check->bind_param("is", $user_id, $taskName);
                    $check->execute();
                    $check_result = $check->get_result();

                    if ($check_result->num_rows > 0) {
                        echo "<div class='alert alert-danger text-center mt-3'>
                                You have already completed this task ($taskName)!
                              </div>";
                    } else {
                        // کاربر این تسک را قبلاً نگرفته، پس پاداش بده
                        $new_oil = $current_oil + $reward;

                        // شروع تراکنش برای اطمینان از یکپارچگی
                        $conn->begin_transaction();
                        try {
                            // آپدیت مقدار نفت در جدول users
                            $update = $conn->prepare("UPDATE users SET oil_drops = ? WHERE id = ?");
                            $update->bind_param("ii", $new_oil, $user_id);
                            if (!$update->execute()) {
                                throw new Exception("Error updating oil drops: " . $update->error);
                            }

                            // درج رکورد در جدول task_completions
                            $task_id = 1; // می‌تونی این رو به صورت پویا تنظیم کنی
                            $insert = $conn->prepare("INSERT INTO task_completions (user_id, task_id, task_name, reward) VALUES (?, ?, ?, ?)");
                            $insert->bind_param("iisi", $user_id, $task_id, $taskName, $reward);
                            if (!$insert->execute()) {
                                throw new Exception("Error inserting task completion: " . $insert->error);
                            }

                            $conn->commit();
                            error_log("Task completed successfully for user ID $user_id - task: $taskName, reward: $reward");

                            // پیام موفقیت و به‌روزرسانی مقدار فعلی نفت
                            echo "<div class='alert alert-success text-center mt-3'>
                                    You earned $reward oil drops ($taskName)!
                                  </div>";
                            $current_oil = $new_oil;
                            $task_completed = true;
                        } catch (Exception $e) {
                            $conn->rollback();
                            error_log("Transaction error in earn.php: " . $e->getMessage());
                            echo "<div class='alert alert-danger text-center mt-3'>
                                    Error completing task: " . $e->getMessage() . "
                                  </div>";
                        }

                        $update->close();
                        $insert->close();
                    }
                    $check->close();
                } else {
                    echo "<div class='alert alert-danger text-center mt-3'>
                            You have not completed this task ($taskName) yet. Please follow the instructions.
                          </div>";
                }
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>
                        API function for $taskName not found.
                      </div>";
            }
            break; // از حلقه خارج شو، چون فقط یکی از تسک‌ها می‌تواند همزمان ست شود
        }
    }

    if ($task_completed) {
        include 'footer.php';
        exit();
    }

    // بررسی ارسال فرم تسک تبلیغاتی
    if (isset($_POST['submit_promo'])) {
        $promo_link = filter_var($_POST['promo_link'], FILTER_SANITIZE_URL);
        if ($promo_link) {
            // درج درخواست در جدول جدید (مثلاً promo_requests)
            $insert_promo = $conn->prepare("INSERT INTO promo_requests (user_id, promo_link, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $insert_promo->bind_param("is", $user_id, $promo_link);
            if ($insert_promo->execute()) {
                echo "<div class='alert alert-info text-center mt-3'>
                        Your promotion request has been submitted and is pending review.
                      </div>";
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>
                        Error submitting promotion request: " . $insert_promo->error . "
                      </div>";
            }
            $insert_promo->close();
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>
                    Invalid promotion link provided.
                  </div>";
        }
        include 'footer.php';
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Earn - Oil Drop Miner</title>
  <!-- لینک‌های CSS و Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .earn-text {
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

    .alert-info {
      background-color: #17a2b8 !important; /* آبی برای پیام‌های اطلاعاتی */
      border-color: #117a8b !important;
    }

    .btn-primary, .btn-info, .btn-danger, .btn-warning, .btn-success {
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
    <h2 class="mb-3 text-center text-warning">Earn More Oil Drops</h2>
    <p class="earn-text">Your Current Oil: <span class="fw-bold"><?php echo $current_oil; ?></span></p>

    <!-- تسک تلگرام -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="join_telegram" class="btn btn-primary">
           <img src="assets/images/tasks/telegram_task_icon.jpg" alt="Telegram" width="30" height="30"> Join Telegram (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- تسک X (Twitter سابق) -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="follow_x" class="btn btn-info">
           <img src="assets/images/tasks/twitter_task_icon.jpg" alt="X" width="30" height="30">
           Follow X (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- تسک یوتیوب -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="subscribe_youtube" class="btn btn-danger">
           <img src="assets/images/tasks/youtube_task_icon.jpg" alt="YouTube" width="30" height="30"> Subscribe YouTube (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- تسک اینستاگرام -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="follow_instagram" class="btn btn-warning">
           <img src="assets/images/tasks/instagram_task_icon.jpg" alt="Instagram" width="30" height="30"> Follow Instagram (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- تسک جدید: تبلیغ پروژه در شبکه‌های اجتماعی با پاداش 10000 -->
    <form method="post" class="mb-3 text-center" id="promo-task-form">
        <div class="mb-3">
            <label for="promo_link" class="form-label text-white">Upload Promotion Link (Reward: 10000 Oil Drops)</label>
            <input type="url" class="form-control bg-dark text-white" id="promo_link" name="promo_link" placeholder="Enter your promotion link" required>
        </div>
        <button type="submit" name="submit_promo" class="btn btn-success">
            <i class="bi bi-upload"></i> Submit Promotion Link
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>
    <?php
    // بررسی ارسال فرم تسک تبلیغاتی
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_promo'])) {
        $promo_link = filter_var($_POST['promo_link'], FILTER_SANITIZE_URL);
        if ($promo_link) {
            // درج درخواست در جدول جدید (مثلاً promo_requests)
            $insert_promo = $conn->prepare("INSERT INTO promo_requests (user_id, promo_link, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $insert_promo->bind_param("is", $user_id, $promo_link);
            if ($insert_promo->execute()) {
                echo "<div class='alert alert-info text-center mt-3'>
                        Your promotion request has been submitted and is pending review.
                      </div>";
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>
                        Error submitting promotion request: " . $insert_promo->error . "
                      </div>";
            }
            $insert_promo->close();
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>
                    Invalid promotion link provided.
                  </div>";
        }
        include 'footer.php';
        exit();
    }
    ?>
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