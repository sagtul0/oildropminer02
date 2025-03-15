<?php
session_start();

// توکن بات
$botToken = '7534598415:AAGL1ehLufaVTLZ_rsqOy3Li2nJTHJ28CoE'; // توکن بات رو از محیط Render یا فایل تنظیمات بگیر

// گرفتن URL دیتابیس از متغیر محیطی
$database_url = getenv('DATABASE_URL');
error_log("DATABASE_URL: $database_url");

if (!$database_url) {
    error_log("DATABASE_URL is not set at " . date('Y-m-d H:i:s'));
    die("DATABASE_URL not found. Please check Render environment variables.");
}

// تجزیه URL دیتابیس
$url_parts = explode('@', str_replace('postgres://', '', $database_url));
if (count($url_parts) < 2) {
    error_log("Invalid DATABASE_URL format at " . date('Y-m-d H:i:s') . " - " . $database_url);
    die("Invalid DATABASE_URL format.");
}

$credentials = explode(':', $url_parts[0]);
$user = $credentials[0];
$pass = $credentials[1];
$host_port_db = explode('/', $url_parts[1]);
$host_port = explode(':', $host_port_db[0]);
$host = $host_port[0];
$port = $host_port[1] ?? '5432';
$dbname = $host_port_db[1];

error_log("Parsed: user=$user, pass=****, host=$host, port=$port, dbname=$dbname");

// اتصال به دیتابیس
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful at " . date('Y-m-d H:i:s'));
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    $conn = null;
    die("Database connection failed: " . $e->getMessage());
}

// تولید CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// مدیریت درخواست‌های setInitData
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], 'setInitData') !== false) {
    header('Content-Type: application/json');
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    error_log("Received InitData from client: " . print_r($data, true));

    if ($data && isset($data['user']) && isset($data['user']['id'])) {
        $chat_id = $data['user']['id'];
        $_SESSION['chat_id'] = $chat_id;
        error_log("Chat ID set from client InitData: " . $chat_id);

        // ثبت کاربر در دیتابیس اگه وجود نداشته باشه
        try {
            $stmt = $conn->prepare("INSERT INTO users (chat_id, oil_drops, balance, created_at) VALUES (:chat_id, 0, 0, NOW()) ON CONFLICT (chat_id) DO NOTHING");
            $stmt->execute(['chat_id' => $chat_id]);
        } catch (PDOException $e) {
            error_log("Database error while registering user: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit;
        }

        // ارسال پاسخ با URL ریدایرکت
        echo json_encode(['success' => true, 'redirect_url' => 'https://oildropminer02-eay2.onrender.com/webapp.php']);
    } else {
        error_log("No user ID in client InitData or data is invalid at " . date('Y-m-d H:i:s'));
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No user ID found or invalid data']);
    }
    exit;
}
?>