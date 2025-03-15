<?php
session_start();

// متغیر محیطی DATABASE_URL از Render
$database_url = getenv('DATABASE_URL');
error_log("DATABASE_URL: $database_url");

// اتصال به دیتابیس PostgreSQL
try {
    if (!$database_url) {
        error_log("DATABASE_URL is not set at " . date('Y-m-d H:i:s'));
        die("DATABASE_URL not found. Please check Render environment variables.");
    }

    // جدا کردن بخش‌های DATABASE_URL
    $db_params = parse_url($database_url);
    if (!$db_params) {
        error_log("Failed to parse DATABASE_URL at " . date('Y-m-d H:i:s'));
        die("Failed to parse DATABASE_URL.");
    }

    $host = $db_params['host'] ?? '';
    $port = $db_params['port'] ?? '5432';
    $dbname = ltrim($db_params['path'] ?? '', '/');
    $user = $db_params['user'] ?? '';
    $pass = $db_params['pass'] ?? '';

    if (empty($host) || empty($dbname) || empty($user) || empty($pass)) {
        error_log("Missing database credentials at " . date('Y-m-d H:i:s') . " - " . print_r($db_params, true));
        die("Missing database credentials. Check DATABASE_URL.");
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful at " . date('Y-m-d H:i:s'));
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    $conn = null;
    die("Database connection failed: " . $e->getMessage());
}

// تنظیم CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تابع برای تنظیم InitData
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], 'setInitData') !== false) {
    header('Content-Type: application/json');
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    error_log("Received InitData from client: " . print_r($data, true));

    if ($data && isset($data['user']) && isset($data['user']['id'])) {
        $_SESSION['chat_id'] = $data['user']['id'];
        error_log("Chat ID set from client InitData: " . $_SESSION['chat_id']);
        echo json_encode(['success' => true, 'chat_id' => $_SESSION['chat_id']]);
    } else {
        error_log("No user ID in client InitData or data is invalid at " . date('Y-m-d H:i:s'));
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No user ID found or invalid data']);
    }
    exit;
}
?>