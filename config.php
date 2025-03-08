<?php
// config.php برای Render با PostgreSQL
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

$conn_string = "host=$hostname port=$port dbname=$database user=$username password=$password";
$conn = pg_connect($conn_string);

if (!$conn) {
    error_log("Connection failed: " . pg_last_error());
    die("Connection failed: " . pg_last_error());
}
?>