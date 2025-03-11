<?php
include 'header.php'; // Includes database connection as $conn

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

// Define costs and rewards arrays
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

// Fetch user data from the database
$stmt = $conn->prepare("SELECT oil_drops, balance, invite_reward FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $oil_drops = (int)$user['oil_drops'];
    $balance = (float)$user['balance'];
    $referrals = (int)$user['invite_reward'];
    error_log("User Data - user_id: $user_id, oil_drops: $oil_drops, balance: $balance, referrals: $referrals");
} else {
    error_log("User not found for ID/Chat_ID: $user_id in oil_cards.php");
    echo "<div class='alert alert-danger text-center mt-3'>User not found.</div>";
    include 'footer.php';
    exit;
}

// Fetch active cards for the user
$active_cards_stmt = $conn->prepare("SELECT card_name, card_type FROM user_cards WHERE user_id = ? AND is_active = 1");
$active_cards_stmt->bind_param("i", $user_id);
$active_cards_stmt->execute();
$active_cards_result = $active_cards_stmt->get_result();
$active_cards = [];
while ($row = $active_cards_result->fetch_assoc()) {
    $active_cards[$row['card_type'] . '_' . $row['card_name']] = true;
}
$active_cards_stmt->close();

// Process card unlocking (only in Web App)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['chat_id'])) {
        echo json_encode(['success' => false, 'message' => 'Card purchases (with Oil Drops or TON) can only be done via Telegram Web App!']);
        exit;
    }

    $card_id = filter_var($_POST['card_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $card_type = filter_var($_POST['card_type'] ?? '', FILTER_SANITIZE_STRING);

    if ($card_type === 'oil') {
        $needs_referral = [5, 10, 15, 20, 25, 30];
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

        if (isset($oil_costs[$card_id]) && isset($oil_card_names[$card_id])) {
            $cost = $oil_costs[$card_id];
            $card_name = $oil_card_names[$card_id];
            $needs_new_referral = in_array($card_id, $needs_referral);

            error_log("Oil Card ID: $card_id, Cost: $cost, Needs Referral: " . ($needs_new_referral ? 'Yes' : 'No') . ", Referrals: $referrals");

            if ($oil_drops >= $cost && (!$needs_new_referral || $referrals >= ($card_id / 5))) {
                // Deduct oil drops from user
                $new_oil = $oil_drops - $cost;
                $update_stmt = $conn->prepare("UPDATE users SET oil_drops = ? WHERE id = ? OR chat_id = ?");
                $update_stmt->bind_param("iii", $new_oil, $user_id, $user_id);

                // Register card in the database
                $reward = [1 => 150, 2 => 530, 3 => 300, 4 => 450, 5 => 900, 6 => 500, 7 => 350, 8 => 400, 9 => 550, 10 => 1000,
                           11 => 650, 12 => 750, 13 => 850, 14 => 950, 15 => 1100, 16 => 1250, 17 => 1350, 18 => 1450, 19 => 1550, 20 => 1650,
                           21 => 1750, 22 => 1850, 23 => 1950, 24 => 2050, 25 => 2150, 26 => 2250, 27 => 2350, 28 => 2450, 29 => 2550, 30 => 2650][$card_id];
                
                $conn->begin_transaction();
                try {
                    if (!$update_stmt->execute()) {
                        throw new Exception("Error updating oil drops: " . $update_stmt->error);
                    }

                    $insert_card_stmt = $conn->prepare("INSERT INTO user_cards (user_id, card_name, card_type, is_active, activation_date) VALUES (?, ?, 'oil', 1, NOW())");
                    $insert_card_stmt->bind_param("is", $user_id, $card_name);
                    if (!$insert_card_stmt->execute()) {
                        throw new Exception("Error inserting card: " . $insert_card_stmt->error);
                    }

                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => "Card unlocked successfully! You will earn $reward Oil Drops every 8 hours.", 'oil_drops' => $new_oil]);
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Transaction error in oil_cards.php: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error unlocking card. Please try again later.']);
                }

                $update_stmt->close();
                $insert_card_stmt->close();
            } else {
                $error_msg = "Not enough Oil Drops or referrals to unlock this card. (Oil: $oil_drops, Cost: $cost, Referrals: $referrals)";
                error_log($error_msg);
                echo json_encode(['success' => false, 'message' => $error_msg]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid card ID.']);
        }
    } elseif ($card_type === 'ton') {
        $ton_card_names = [
            1 => "TON Rig Boost", 2 => "TON Refinery Power", 3 => "TON Tanker Surge", 4 => "TON Drill Power",
            5 => "TON Pipeline Boost", 6 => "TON Offshore Boost", 7 => "TON Storage Surge", 8 => "TON Flare Power",
            9 => "TON Seismic Boost", 10 => "TON Pumpjack Surge"
        ];

        if (isset($ton_costs[$card_id]) && isset($ton_card_names[$card_id])) {
            $cost = $ton_costs[$card_id];
            $card_name = $ton_card_names[$card_id];

            if ($balance >= $cost) {
                // Deduct TON from user's balance
                $new_balance = $balance - $cost;
                $update_stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ? OR chat_id = ?");
                $update_stmt->bind_param("dii", $new_balance, $user_id, $user_id);

                // Register card in the database
                $reward = $ton_rewards[$card_id];
                
                $conn->begin_transaction();
                try {
                    if (!$update_stmt->execute()) {
                        throw new Exception("Error updating balance: " . $update_stmt->error);
                    }

                    $insert_card_stmt = $conn->prepare("INSERT INTO user_cards (user_id, card_name, card_type, is_active, activation_date) VALUES (?, ?, 'ton', 1, NOW())");
                    $insert_card_stmt->bind_param("is", $user_id, $card_name);
                    if (!$insert_card_stmt->execute()) {
                        throw new Exception("Error inserting card: " . $insert_card_stmt->error);
                    }

                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => "TON Card unlocked successfully! You will earn $reward Oil Drops every 8 hours.", 'balance' => $new_balance]);
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Transaction error in oil_cards.php: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error unlocking card. Please try again later.']);
                }

                $update_stmt->close();
                $insert_card_stmt->close();
            } else {
                $error_msg = "Not enough TON balance to unlock this card. (Balance: $balance, Cost: $cost)";
                error_log($error_msg);
                echo json_encode(['success' => false, 'message' => $error_msg]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid TON card ID.']);
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oil Cards - Oil Drop Miner</title>
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
            width: 50px;
            height: auto;
            margin-right: 5px;
        }
        .nav-link { font-size: 1rem !important; color: #ffffff !important; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5); }
        .nav-link:hover { color: #ffcc00 !important; }
        .container {
            max-width: 1200px;
            margin-top: 80px !important;
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
            max-height: calc(100vh - 400px);
            overflow-y: auto;
            padding-bottom: 20px;
        }
        .card {
            background: rgba(30, 30, 30, 0.9);
            border: 2px solid #D4A017;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            width: 100%;
            height: 350px;
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
            height: 150px;
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
            z-index: 10;
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
        .card-active {
            background-color: #28a745 !important;
            border-color: #1e7e34 !important;
            cursor: default !important;
        }
        .card-active:hover {
            transform: none !important;
            box-shadow: none !important;
            background-color: #218838 !important;
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
                    <?php if (isset($_SESSION['user_id']) || isset($_SESSION['chat_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="earn.php">Earn</a></li>
                        <li class="nav-item"><a class="nav-link" href="invite.php">Invite</a></li>
                        <li class="nav-item"><a class="nav-link" href="plans.php">Plans</a></li>
                        <li class="nav-item"><a class="nav-link" href="oil_cards.php">Oil Cards</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login_web.php">Login</a></li>
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
            <li class="nav-item" role="presentation">
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
                        $card_key = 'oil_' . $oil_card_names[$i];
                        $is_active = isset($active_cards[$card_key]);
                    ?>
                        <div class="card" data-card-id="<?php echo $i; ?>" data-card-type="oil">
                            <img src="assets/images/oil_cards/<?php echo htmlspecialchars($oil_card_names[$i]); ?>.jpg" alt="<?php echo htmlspecialchars($oil_card_names[$i]); ?>">
                            <h5 class="card-title"><?php echo htmlspecialchars($oil_card_names[$i]); ?></h5>
                            <p class="card-cost">Cost: <?php echo $oil_costs[$i]; ?> Oil Drops
                                <?php if (in_array($i, [5, 10, 15, 20, 25, 30])) echo " + 1 New Referral"; ?></p>
                            <p class="card-reward">Reward: <?php echo [1 => 150, 2 => 530, 3 => 300, 4 => 450, 5 => 900, 6 => 500, 7 => 350, 8 => 400, 9 => 550, 10 => 1000,
                                                                    11 => 650, 12 => 750, 13 => 850, 14 => 950, 15 => 1100, 16 => 1250, 17 => 1350, 18 => 1450, 19 => 1550, 20 => 1650,
                                                                    21 => 1750, 22 => 1850, 23 => 1950, 24 => 2050, 25 => 2150, 26 => 2250, 27 => 2350, 28 => 2450, 29 => 2550, 30 => 2650][$i]; ?> Oil Drops / 8h</p>
                            <?php if ($is_active): ?>
                                <button class="btn btn-warning card-active" disabled>Active</button>
                            <?php else: ?>
                                <button class="btn btn-warning" onclick="unlockCard(<?php echo $i; ?>, 'oil')" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>>Unlock</button>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <?php if (!isset($_SESSION['chat_id'])): ?>
                    <p class="text-warning text-center mt-3">Card purchases (including those with Oil Drops) can only be done via Telegram Web App!</p>
                <?php endif; ?>
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
                        $card_key = 'ton_' . $ton_card_names[$i];
                        $is_active = isset($active_cards[$card_key]);
                    ?>
                        <div class="card" data-card-id="<?php echo $i; ?>" data-card-type="ton">
                            <img src="assets/images/oil_cards/<?php echo htmlspecialchars($ton_card_names[$i]); ?>.jpg" alt="<?php echo htmlspecialchars($ton_card_names[$i]); ?>">
                            <h5 class="card-title"><?php echo htmlspecialchars($ton_card_names[$i]); ?></h5>
                            <p class="card-cost">Cost: <?php echo $ton_costs[$i]; ?> TON</p>
                            <p class="card-reward">Reward: <?php echo $ton_rewards[$i]; ?> Oil Drops / 8h</p>
                            <?php if ($is_active): ?>
                                <button class="btn btn-warning card-active" disabled>Active</button>
                            <?php else: ?>
                                <button class="btn btn-warning" onclick="unlockCard(<?php echo $i; ?>, 'ton')" <?php if (!isset($_SESSION['chat_id'])) echo "disabled"; ?>>Unlock</button>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <?php if (!isset($_SESSION['chat_id'])): ?>
                    <p class="text-warning text-center mt-3">Card purchases (including those with TON) can only be done via Telegram Web App!</p>
                <?php endif; ?>
            </div>

            <!-- Tab 3: New Cards -->
            <div class="tab-pane fade" id="new" role="tabpanel" aria-labelledby="new-tab">
                <div class="cards-grid">
                    <p class="text-center text-white">New cards will be added here as updates are released!</p>
                </div>
            </div>
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
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('An error occurred while unlocking the card. Please try again later.');
                });
            }
        }

        function numberFormat(number, decimals) {
            return number.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

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