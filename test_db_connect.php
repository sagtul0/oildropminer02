<?php
ini_set('display_errors', 1);
error_log("Script started at " . date('Y-m-d H:i:s'));

$database_url = "postgres://oildropminer_db_user:WXzD1SqGI9Vx8nZ966VK4dUNH1p6f2QG@oregon-postgres.render.com:5432/oildropminer_db";
error_log("Testing DATABASE_URL: $database_url");

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

error_log("Parsed DB params: " . print_r($db_params, true));
if (empty($host) || empty($dbname) || empty($user) || empty($pass)) {
    error_log("Missing credentials: host=$host, dbname=$dbname, user=$user, pass=" . ($pass ? "set" : "empty"));
    die("Missing database credentials. Check DATABASE_URL.");
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "اتصال موفق!";
    error_log("Database connection successful at " . date('Y-m-d H:i:s'));
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    die("Database connection failed: " . $e->getMessage());
}
?>