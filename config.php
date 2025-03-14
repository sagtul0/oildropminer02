<?php
session_start();

// متغیرهای محیطی از Render
$database_url = getenv('DATABASE_URL');
$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUsername = getenv('DB_USERNAME');
$dbPassword = getenv('DB_PASSWORD');

error_log("DB_HOST: $dbHost, DB_NAME: $dbName, DB_USERNAME: $dbUsername");

// اتصال به دیتابیس PostgreSQL
try {
    if ($database_url) {
        // اگر DATABASE_URL تنظیم شده باشه، از اون استفاده می‌کنیم
        $conn = new PDO($database_url);
        error_log("Connected using DATABASE_URL at " . date('Y-m-d H:i:s'));
    } else {
        // در غیر این صورت، از متغیرهای جداگانه استفاده می‌کنیم
        if (!$dbHost || !$dbName || !$dbUsername || !$dbPassword) {
            error_log("Database environment variables (DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD) are not set at " . date('Y-m-d H:i:s'));
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database environment variables not found']);
            exit;
        }

        $dsn = "pgsql:host=$dbHost;port=5432;dbname=$dbName";
        $conn = new PDO($dsn, $dbUsername, $dbPassword);
        error_log("Connected using individual DB variables at " . date('Y-m-d H:i:s'));
    }

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful at " . date('Y-m-d H:i:s'));
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    $conn = null;
}

if ($conn === null) {
    error_log("Connection is null after including config.php at " . date('Y-m-d H:i:s'));
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection not established. Check server logs.']);
    exit;
}

// تنظیم CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تابع برای تنظیم InitData (جایگزین setInitData.php)
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