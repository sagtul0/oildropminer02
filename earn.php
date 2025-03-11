<?php
include 'header.php'; // Session starts here and styles are loaded
include 'config/social_api.php'; // Social media API functions

// Check if user is logged in (support both user_id and chat_id for Web App)
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
                exit;
            }
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>Please <a href='login_web.php'>login</a> with TON address.</div>";
            include 'footer.php';
            exit;
        }
    } else {
        echo "<div class='alert alert-danger text-center mt-3'>Please <a href='login_web.php'>login</a> with TON address.</div>";
        include 'footer.php';
        exit;
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

// Fetch user data from the database
$stmt = $conn->prepare("SELECT oil_drops FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("User not found for ID/Chat_ID: $user_id in earn.php");
    echo "<div class='alert alert-danger text-center mt-3'>User not found.</div>";
    include 'footer.php';
    exit;
}
$user = $result->fetch_assoc();
$current_oil = (int)$user["oil_drops"];

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submissions (POST requests)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Rate Limiting: Check number of attempts in the last 5 minutes
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM task_attempts WHERE user_id = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['attempts'] >= 5) {
        echo "<div class='alert alert-warning text-center mt-3'>Too many attempts. Please try again later.</div>";
        include 'footer.php';
        exit;
    }

    // Log the current attempt
    $stmt = $conn->prepare("INSERT INTO task_attempts (user_id, attempt_time) VALUES (?, NOW())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch for user ID: $user_id");
        echo "<div class='alert alert-danger text-center mt-3'>CSRF token mismatch. Please try again.</div>";
        include 'footer.php';
        exit;
    }

    // Define tasks: [task key => [task name in DB, reward, check function]]
    $tasks = [
        "join_telegram"     => ["telegram", 10, 'checkTelegramMembership'],
        "follow_x"          => ["x", 10, 'checkXFollow'],
        "subscribe_youtube" => ["youtube", 10, 'checkYouTubeSubscription'],
        "follow_instagram"  => ["instagram", 10, 'checkInstagramFollow']
    ];

    // Check which task is being submitted
    $task_completed = false;
    foreach ($tasks as $postKey => $taskData) {
        if (isset($_POST[$postKey])) {
            $taskName = $taskData[0];
            $reward = $taskData[1];
            $checkFunction = $taskData[2];

            // Verify task completion using API (or manual verification as a fallback)
            if (function_exists($checkFunction)) {
                if ($checkFunction($user_id)) {
                    // Check if the user has already completed this task
                    $check = $conn->prepare("SELECT id FROM task_completions WHERE user_id = ? AND task_name = ?");
                    $check->bind_param("is", $user_id, $taskName);
                    $check->execute();
                    $check_result = $check->get_result();

                    if ($check_result->num_rows > 0) {
                        echo "<div class='alert alert-danger text-center mt-3'>
                                You have already completed the $taskName task!
                              </div>";
                    } else {
                        // User hasn't completed this task yet, proceed with reward
                        $new_oil = $current_oil + $reward;

                        // Start a transaction for data integrity
                        $conn->begin_transaction();
                        try {
                            // Update oil drops in the users table
                            $update = $conn->prepare("UPDATE users SET oil_drops = ? WHERE id = ? OR chat_id = ?");
                            $update->bind_param("iii", $new_oil, $user_id, $user_id);
                            if (!$update->execute()) {
                                throw new Exception("Error updating oil drops: " . $update->error);
                            }

                            // Insert record into task_completions table
                            $task_id = 1; // You can make this dynamic based on your DB structure
                            $insert = $conn->prepare("INSERT INTO task_completions (user_id, task_id, task_name, reward) VALUES (?, ?, ?, ?)");
                            $insert->bind_param("iisi", $user_id, $task_id, $taskName, $reward);
                            if (!$insert->execute()) {
                                throw new Exception("Error inserting task completion: " . $insert->error);
                            }

                            $conn->commit();
                            error_log("Task completed successfully for user ID $user_id - task: $taskName, reward: $reward");

                            // Success message and update current oil
                            echo "<div class='alert alert-success text-center mt-3'>
                                    You earned $reward Oil Drops for completing the $taskName task!
                                  </div>";
                            $current_oil = $new_oil;
                            $task_completed = true;
                        } catch (Exception $e) {
                            $conn->rollback();
                            error_log("Transaction error in earn.php: " . $e->getMessage());
                            echo "<div class='alert alert-danger text-center mt-3'>
                                    Error completing task. Please try again later.
                                  </div>";
                        }

                        $update->close();
                        $insert->close();
                    }
                    $check->close();
                } else {
                    echo "<div class='alert alert-danger text-center mt-3'>
                            You have not completed the $taskName task yet. Please follow the instructions.
                          </div>";
                }
            } else {
                echo "<div class='alert alert-danger text-center mt-3'>
                        Verification for $taskName task is not available at the moment.
                      </div>";
            }
            break; // Exit loop as only one task can be submitted at a time
        }
    }

    // Process promotional task submission
    if (isset($_POST['submit_promo'])) {
        $promo_link = filter_var($_POST['promo_link'], FILTER_SANITIZE_URL);
        if (!filter_var($promo_link, FILTER_VALIDATE_URL)) {
            echo "<div class='alert alert-danger text-center mt-3'>
                    Invalid promotion link provided.
                  </div>";
        } else {
            // Check if the user has already submitted a promo link
            $check_promo = $conn->prepare("SELECT id FROM promo_requests WHERE user_id = ? AND status = 'pending'");
            $check_promo->bind_param("i", $user_id);
            $check_promo->execute();
            $promo_result = $check_promo->get_result();

            if ($promo_result->num_rows > 0) {
                echo "<div class='alert alert-warning text-center mt-3'>
                        You already have a pending promotion request. Please wait for review.
                      </div>";
            } else {
                // Insert the promo request into the database
                $insert_promo = $conn->prepare("INSERT INTO promo_requests (user_id, promo_link, status, created_at) VALUES (?, ?, 'pending', NOW())");
                $insert_promo->bind_param("is", $user_id, $promo_link);
                if ($insert_promo->execute()) {
                    echo "<div class='alert alert-info text-center mt-3'>
                            Your promotion request has been submitted and is pending review. You will earn 10000 Oil Drops upon approval.
                          </div>";
                } else {
                    error_log("Error submitting promo request for user ID $user_id: " . $insert_promo->error);
                    echo "<div class='alert alert-danger text-center mt-3'>
                            Error submitting promotion request. Please try again later.
                          </div>";
                }
                $insert_promo->close();
            }
            $check_promo->close();
        }
        include 'footer.php';
        exit;
    }

    if ($task_completed) {
        include 'footer.php';
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Earn - Oil Drop Miner</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    .earn-text { color: #ffffff !important; font-weight: bold !important; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8) !important; font-size: 1.2rem !important; }
    .alert { color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; }
    .alert-success { background-color: #28a745 !important; border-color: #1e7e34 !important; }
    .alert-danger { background-color: #dc3545 !important; border-color: #bd2130 !important; }
    .alert-info { background-color: #17a2b8 !important; border-color: #117a8b !important; }
    .alert-warning { background-color: #ffcc00 !important; border-color: #e6b800 !important; }
    .btn-primary, .btn-info, .btn-danger, .btn-warning, .btn-success { color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; }
    .container { max-width: 1000px; margin-top: 20px !important; padding-top: 0 !important; }
    body::before { background: rgba(0, 0, 0, 0.5) !important; }
    #particles-js { z-index: 0 !important; }
  </style>
</head>
<body>
  <div id="particles-js"></div>

  <div class="container mt-5">
    <h2 class="mb-3 text-center text-warning">Earn More Oil Drops</h2>
    <p class="earn-text text-center">Your Current Oil: <span class="fw-bold"><?php echo $current_oil; ?></span></p>

    <?php if (!isset($_SESSION['chat_id'])): ?>
        <p class="text-warning text-center mt-3">You can complete tasks in the browser, but we recommend using the Telegram Web App for a better experience!</p>
    <?php endif; ?>

    <!-- Telegram Task -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="join_telegram" class="btn btn-primary">
           <img src="assets/images/tasks/telegram_task_icon.jpg" alt="Telegram" width="30" height="30"> Join Telegram (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- X Task -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="follow_x" class="btn btn-info">
           <img src="assets/images/tasks/twitter_task_icon.jpg" alt="X" width="30" height="30"> Follow X (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- YouTube Task -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="subscribe_youtube" class="btn btn-danger">
           <img src="assets/images/tasks/youtube_task_icon.jpg" alt="YouTube" width="30" height="30"> Subscribe YouTube (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- Instagram Task -->
    <form method="post" class="mb-3 text-center">
        <button type="submit" name="follow_instagram" class="btn btn-warning">
           <img src="assets/images/tasks/instagram_task_icon.jpg" alt="Instagram" width="30" height="30"> Follow Instagram (+10)
        </button>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <!-- Promotional Task -->
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