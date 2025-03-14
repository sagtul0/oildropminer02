<?php
include 'config.php';
try {
    $result = $pdo->query("SELECT 1")->fetch();
    echo "Database connection successful: " . print_r($result, true);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>