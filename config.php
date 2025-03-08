<?php
// config.php
$db_url = getenv('DATABASE_URL');
if (!$db_url) {
    die("Error: DATABASE_URL not set in environment variables!");
}

$dbparts = parse_url($db_url);

$hostname = $dbparts['host'];
$username = $dbparts['user'];
$password = $dbparts['pass'];
$database = ltrim($dbparts['path'], '/');
$port = $dbparts['port'];

$conn = new mysqli($hostname, $username, $password, $database, $port);

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}
?>