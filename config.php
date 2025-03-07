<?php
// اطلاعات دیتابیس از Render
$conn_string = "host=dpg-cv4gtnij1k6c73bjrrhg-a.oregon-postgres.render.com port=5432 dbname=oildropminer_db user=oildropminer_db_user password=WXzD1SqGI9Vx8nZ966VK4dUNH1p6f2QGWXzD1SqGI9Vx8nZ966VK4dUNH1p6f2QG";

// اتصال به دیتابیس PostgreSQL
$conn = pg_connect($conn_string);

// چک کردن اتصال
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// تنظیم کدگذاری برای پشتیبانی از فارسی
pg_set_client_encoding($conn, "UTF8");

// می‌تونی اینجا کدهای دیگه‌ت (مثل تلگرام) رو اضافه کنی
?>
