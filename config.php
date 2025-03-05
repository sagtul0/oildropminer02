<?php
$host = "localhost";
$username = "root"; // نام کاربری دیتابیس (معمولاً root در XAMPP)
$password = ""; // رمز عبور دیتابیس (در XAMPP معمولاً خالی است)
$database = "oil_drop_miner"; // نام دیتابیس

// اتصال به دیتابیس
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}
?>