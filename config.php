<?php
$host = getenv('DB_HOST') ?: 'postgres-render.com';
$dbname = getenv('DB_NAME') ?: 'oildropminer_db';
$username = getenv('DB_USERNAME') ?: 'oildropminer_db_user';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "اتصال به دیتابیس موفق بود!";
} catch (PDOException $e) {
    die("خطا در اتصال: " . $e->getMessage());
}
?>