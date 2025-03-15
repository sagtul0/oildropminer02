<?php
session_start();

$database_url = getenv('DATABASE_URL');
error_log("DATABASE_URL: $database_url");

if (!$database_url) {
    error_log("DATABASE_URL is not set at " . date('Y-m-d H:i:s'));
    die("DATABASE_URL not found. Please check Render environment variables.");
}

// جدا کردن دستی بخش‌ها
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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