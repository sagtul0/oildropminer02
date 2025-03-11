<?php
$host = getenv('DB_HOST');
if (!$host) {
    error_log("Error: DB_HOST environment variable not set.");
    die("Server error. Please try again later.");
}

$dbname = getenv('DB_NAME');
if (!$dbname) {
    error_log("Error: DB_NAME environment variable not set.");
    die("Server error. Please try again later.");
}

$username = getenv('DB_USERNAME');
if (!$username) {
    error_log("Error: DB_USERNAME environment variable not set.");
    die("Server error. Please try again later.");
}

$password = getenv('DB_PASSWORD');
if (!$password) {
    error_log("Error: DB_PASSWORD environment variable not set.");
    die("Server error. Please try again later.");
}

try {
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful!");
} catch (PDOException $e) {
    error_log("Connection error: " . $e->getMessage());
    die("Server error. Please try again later.");
}
?>