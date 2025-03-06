<?php
$host = 'your-postgres-host.render.com'; // از اطلاعات اتصال
$db_name = 'oildropminer_db';
$username = 'your-username';
$password = 'your-password';
$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
