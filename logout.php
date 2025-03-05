<?php
include 'config.php';

// شروع سشن اگر فعال نیست
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// پاک کردن سشن
session_unset();
session_destroy();

// هدایت به صفحه لاگین
header("Location: login.php");
exit();
