<?php
$host = getenv('DB_HOST');
if (!$host) {
    die("Error: DB_HOST environment variable not set.");
}

$dbname = getenv('DB_NAME');
if (!$dbname) {
    die("Error: DB_NAME environment variable not set.");
}

$username = getenv('DB_USERNAME');
if (!$username) {
    die("Error: DB_USERNAME environment variable not set.");
}

$password = getenv('DB_PASSWORD');
if (!$password) {
    die("Error: DB_PASSWORD environment variable not set.");
}

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("اتصال به دیتابیس موفق بود!");
} catch (PDOException $e) {
    error_log("خطا در اتصال: " . $e->getMessage());
    die("خطا در اتصال: " . $e->getMessage());
}
?>